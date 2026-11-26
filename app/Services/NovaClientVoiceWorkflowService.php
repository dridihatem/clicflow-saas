<?php

namespace App\Services;

use App\Enums\InquiryStatus;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Inquiry;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Models\WorkspaceCalendarEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Mobile Nova conversational create flows for client users.
 * Slot-fills invoice / event / quote with spoken prompts.
 */
class NovaClientVoiceWorkflowService
{
    private const TTL = 60 * 20;

    public function __construct(
        private InvoiceTotalsService $totals,
        private InvoiceReferenceService $references,
    ) {}

    /**
     * @return array{handled: bool, reply: string, done: bool, workflow: ?string, step: ?string, data?: array<string, mixed>, result?: array<string, mixed>}
     */
    public function handle(User $user, Client $client, Company $company, string $message): array
    {
        $message = trim($message);
        $session = $this->getSession($user->id);

        if ($this->isCancel($message)) {
            $this->clearSession($user->id);

            return [
                'handled' => true,
                'reply' => __('Alright, I cancelled that. What else can I help with?'),
                'done' => true,
                'workflow' => null,
                'step' => null,
            ];
        }

        if ($session !== null) {
            return $this->advance($user, $client, $company, $session, $message);
        }

        $intent = $this->detectIntent($message);
        if ($intent === null) {
            return [
                'handled' => false,
                'reply' => '',
                'done' => false,
                'workflow' => null,
                'step' => null,
            ];
        }

        if ($intent === 'choose') {
            $session = [
                'workflow' => 'choose',
                'step' => 'collect_kind',
                'data' => [],
            ];
            $this->putSession($user->id, $session);

            return [
                'handled' => true,
                'reply' => __('What do you want to create? Say invoice, event, or quote.'),
                'done' => false,
                'workflow' => 'choose',
                'step' => 'collect_kind',
            ];
        }

        return $this->startWorkflow($user, $client, $company, $intent, $message);
    }

    /**
     * @param  array{workflow: string, step: string, data: array<string, mixed>}  $session
     * @return array{handled: bool, reply: string, done: bool, workflow: ?string, step: ?string, data?: array<string, mixed>, result?: array<string, mixed>}
     */
    private function advance(User $user, Client $client, Company $company, array $session, string $message): array
    {
        $workflow = (string) $session['workflow'];

        if ($workflow === 'choose') {
            $kind = $this->detectIntent($message);
            if ($kind === null || $kind === 'choose') {
                return [
                    'handled' => true,
                    'reply' => __('Please say invoice, event, or quote.'),
                    'done' => false,
                    'workflow' => 'choose',
                    'step' => 'collect_kind',
                ];
            }

            return $this->startWorkflow($user, $client, $company, $kind, $message);
        }

        return match ($workflow) {
            'create_invoice' => $this->advanceInvoice($user, $client, $company, $session, $message),
            'create_event' => $this->advanceEvent($user, $client, $company, $session, $message),
            'create_quote' => $this->advanceQuote($user, $client, $company, $session, $message),
            default => $this->clearAndFail($user->id),
        };
    }

    /**
     * @return array{handled: bool, reply: string, done: bool, workflow: ?string, step: ?string, data?: array<string, mixed>, result?: array<string, mixed>}
     */
    private function startWorkflow(User $user, Client $client, Company $company, string $intent, string $message): array
    {
        $data = [];

        if ($intent === 'create_invoice') {
            $data = [
                'currency' => strtoupper((string) ($company->default_currency ?? flowdesk_default_currency())),
            ];
            $data = $this->mergeInvoiceFreeform($data, $message);
            $session = ['workflow' => 'create_invoice', 'step' => 'collect_name', 'data' => $data];
            $this->putSession($user->id, $session);

            return [
                'handled' => true,
                'reply' => __('Creating an invoice. Give me the client name, email and phone — or say them one by one. You can also say "use my profile".'),
                'done' => false,
                'workflow' => 'create_invoice',
                'step' => 'collect_name',
                'data' => $data,
            ];
        }

        if ($intent === 'create_event') {
            $data = $this->mergeEventFreeform([], $message);
            $missing = $this->eventMissing($data);
            $next = $missing[0] ?? 'collect_subject';
            $session = ['workflow' => 'create_event', 'step' => $next, 'data' => $data];
            if ($missing === []) {
                return $this->executeEvent($user, $client, $company, $data);
            }
            $this->putSession($user->id, $session);

            return [
                'handled' => true,
                'reply' => empty($data)
                    ? __('Let\'s create an event. What is the title or subject?')
                    : $this->eventPrompt($next),
                'done' => false,
                'workflow' => 'create_event',
                'step' => $next,
                'data' => $data,
            ];
        }

        // create_quote
        $data = $this->mergeQuoteFreeform([
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
        ], $message);
        $missing = $this->quoteMissing($data);
        $next = $missing[0] ?? 'collect_subject';
        $session = ['workflow' => 'create_quote', 'step' => $next, 'data' => $data];
        if ($missing === []) {
            return $this->executeQuote($user, $client, $company, $data);
        }
        $this->putSession($user->id, $session);

        return [
            'handled' => true,
            'reply' => empty($data['subject'] ?? null)
                ? __('Creating a quote request. What should the subject be?')
                : $this->quotePrompt($next),
            'done' => false,
            'workflow' => 'create_quote',
            'step' => $next,
            'data' => $data,
        ];
    }

    /**
     * @param  array{workflow: string, step: string, data: array<string, mixed>}  $session
     * @return array{handled: bool, reply: string, done: bool, workflow: ?string, step: ?string, data?: array<string, mixed>, result?: array<string, mixed>}
     */
    private function advanceInvoice(User $user, Client $client, Company $company, array $session, string $message): array
    {
        $step = (string) $session['step'];
        $data = is_array($session['data'] ?? null) ? $session['data'] : [];
        $data = $this->applyInvoiceStep($client, $step, $data, $message);
        $missing = $this->invoiceMissing($data);

        if ($missing !== []) {
            $next = $missing[0];
            $session['step'] = $next;
            $session['data'] = $data;
            $this->putSession($user->id, $session);

            return [
                'handled' => true,
                'reply' => $this->invoicePrompt($next, $data, $client),
                'done' => false,
                'workflow' => 'create_invoice',
                'step' => $next,
                'data' => $data,
            ];
        }

        return $this->executeInvoice($user, $client, $company, $data);
    }

    /**
     * @param  array{workflow: string, step: string, data: array<string, mixed>}  $session
     * @return array{handled: bool, reply: string, done: bool, workflow: ?string, step: ?string, data?: array<string, mixed>, result?: array<string, mixed>}
     */
    private function advanceEvent(User $user, Client $client, Company $company, array $session, string $message): array
    {
        $step = (string) $session['step'];
        $data = is_array($session['data'] ?? null) ? $session['data'] : [];
        $data = $this->applyEventStep($step, $data, $message);
        $missing = $this->eventMissing($data);

        if ($missing !== []) {
            $next = $missing[0];
            $session['step'] = $next;
            $session['data'] = $data;
            $this->putSession($user->id, $session);

            return [
                'handled' => true,
                'reply' => $this->eventPrompt($next),
                'done' => false,
                'workflow' => 'create_event',
                'step' => $next,
                'data' => $data,
            ];
        }

        return $this->executeEvent($user, $client, $company, $data);
    }

    /**
     * @param  array{workflow: string, step: string, data: array<string, mixed>}  $session
     * @return array{handled: bool, reply: string, done: bool, workflow: ?string, step: ?string, data?: array<string, mixed>, result?: array<string, mixed>}
     */
    private function advanceQuote(User $user, Client $client, Company $company, array $session, string $message): array
    {
        $step = (string) $session['step'];
        $data = is_array($session['data'] ?? null) ? $session['data'] : [];
        $data = $this->applyQuoteStep($client, $step, $data, $message);
        $missing = $this->quoteMissing($data);

        if ($missing !== []) {
            $next = $missing[0];
            $session['step'] = $next;
            $session['data'] = $data;
            $this->putSession($user->id, $session);

            return [
                'handled' => true,
                'reply' => $this->quotePrompt($next),
                'done' => false,
                'workflow' => 'create_quote',
                'step' => $next,
                'data' => $data,
            ];
        }

        return $this->executeQuote($user, $client, $company, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{handled: bool, reply: string, done: bool, workflow: ?string, step: ?string, result?: array<string, mixed>}
     */
    private function executeInvoice(User $user, Client $client, Company $company, array $data): array
    {
        $name = (string) $data['name'];
        $email = (string) $data['email'];
        $phone = (string) ($data['phone'] ?? '');
        $description = (string) $data['description'];
        $currency = strtoupper((string) $data['currency']);
        $amountMajor = (float) $data['amount'];
        $unitMinor = (int) round($amountMajor * 100);

        $invoice = DB::transaction(function () use ($client, $company, $name, $email, $phone, $description, $currency, $unitMinor) {
            $client->forceFill([
                'name' => $name,
                'email' => $email ?: $client->email,
                'phone' => $phone !== '' ? $phone : $client->phone,
            ])->save();

            $taxes = $this->totals->fromSubtotalMinor($unitMinor, $company);

            $inv = Invoice::query()->withoutGlobalScope('tenant')->create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'proposal_id' => null,
                'project_id' => null,
                'number' => null,
                'status' => InvoiceStatus::Draft,
                'subtotal_amount' => $taxes['subtotal'],
                'vat_amount' => $taxes['vat'],
                'fiscal_stamp_amount' => $taxes['stamp'],
                'amount' => $taxes['total'],
                'currency' => $currency,
                'due_date' => now()->addDays(14)->toDateString(),
                'customer_notes' => __('Created via Nova mobile voice'),
            ]);

            InvoiceItem::query()->withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'invoice_id' => $inv->id,
                ...flowdesk_split_line_label(null, $description),
                'quantity' => 1,
                'unit_amount' => $unitMinor,
                'total_amount' => $unitMinor,
            ]);

            $this->references->assignNextNumber($inv, $company);

            return $inv->fresh();
        });

        $this->clearSession($user->id);
        $ref = $invoice->number ?? $invoice->id;

        return [
            'handled' => true,
            'reply' => __('Done! Draft invoice :ref created for :amount :currency — :desc.', [
                'ref' => $ref,
                'amount' => number_format((float) $data['amount'], 2),
                'currency' => $currency,
                'desc' => $description,
            ]),
            'done' => true,
            'workflow' => null,
            'step' => null,
            'result' => [
                'type' => 'invoice',
                'id' => $invoice->id,
                'reference' => $ref,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{handled: bool, reply: string, done: bool, workflow: ?string, step: ?string, result?: array<string, mixed>}
     */
    private function executeEvent(User $user, Client $client, Company $company, array $data): array
    {
        $event = WorkspaceCalendarEvent::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'title' => (string) $data['subject'],
            'description' => (string) ($data['message'] ?? ''),
            'starts_on' => (string) $data['date'],
            'start_time' => ($data['time'] ?? '') !== '' ? (string) $data['time'] : null,
            'ends_on' => (string) $data['date'],
            'kind' => 'meeting',
            'source_type' => 'mobile_voice',
        ]);

        $this->clearSession($user->id);

        return [
            'handled' => true,
            'reply' => __('Event ":title" is booked for :date:time.', [
                'title' => $event->title,
                'date' => $data['date'],
                'time' => ! empty($data['time']) ? ' at '.$data['time'] : '',
            ]),
            'done' => true,
            'workflow' => null,
            'step' => null,
            'result' => [
                'type' => 'event',
                'id' => $event->id,
                'title' => $event->title,
                'date' => $data['date'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{handled: bool, reply: string, done: bool, workflow: ?string, step: ?string, result?: array<string, mixed>}
     */
    private function executeQuote(User $user, Client $client, Company $company, array $data): array
    {
        $inquiry = Inquiry::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'subject' => (string) $data['subject'],
            'message' => (string) $data['message'],
            'contact_name' => (string) ($data['name'] ?? $client->name),
            'contact_email' => (string) ($data['email'] ?? $client->email),
            'contact_phone' => (string) ($data['phone'] ?? $client->phone),
            'source' => 'mobile_voice',
            'status' => InquiryStatus::New,
        ]);

        $this->clearSession($user->id);

        return [
            'handled' => true,
            'reply' => __('Quote request sent: ":subject". The workspace will follow up.', [
                'subject' => $inquiry->subject,
            ]),
            'done' => true,
            'workflow' => null,
            'step' => null,
            'result' => [
                'type' => 'quote',
                'id' => $inquiry->id,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyInvoiceStep(Client $client, string $step, array $data, string $input): array
    {
        $n = mb_strtolower(trim($input));
        $useProfile = preg_match('/\b(use my profile|my profile|same|me|moi|profil)\b/u', $n) === 1;

        return match ($step) {
            'collect_name' => tap($data, function (&$d) use ($input, $useProfile, $client) {
                if ($useProfile) {
                    $d['name'] = $client->name;
                    $d['email'] = $client->email;
                    $d['phone'] = $client->phone ?? '';

                    return;
                }
                $name = NovaVoiceInputParser::parseNameField($input) ?? NovaVoiceInputParser::extractName($input);
                if ($name) {
                    $d['name'] = $name;
                } elseif (trim($input) !== '' && ! NovaVoiceInputParser::extractEmail($input)) {
                    $d['name'] = mb_substr(trim($input), 0, 255);
                }
                $email = NovaVoiceInputParser::extractEmail($input);
                $phone = NovaVoiceInputParser::extractPhone($input);
                if ($email) {
                    $d['email'] = $email;
                }
                if ($phone) {
                    $d['phone'] = $phone;
                }
            }),
            'collect_email' => tap($data, function (&$d) use ($input, $useProfile, $client) {
                if ($useProfile && $client->email) {
                    $d['email'] = $client->email;

                    return;
                }
                $email = NovaVoiceInputParser::extractEmail($input);
                if ($email) {
                    $d['email'] = $email;
                } elseif (filter_var(trim($input), FILTER_VALIDATE_EMAIL)) {
                    $d['email'] = strtolower(trim($input));
                }
            }),
            'collect_phone' => tap($data, function (&$d) use ($input, $useProfile, $client) {
                if ($this->isSkip($input)) {
                    $d['phone'] = $d['phone'] ?? ($client->phone ?? '');

                    return;
                }
                if ($useProfile) {
                    $d['phone'] = $client->phone ?? '';

                    return;
                }
                $phone = NovaVoiceInputParser::extractPhone($input) ?? trim($input);
                $d['phone'] = $phone;
            }),
            'collect_description' => tap($data, function (&$d) use ($input) {
                $d['description'] = mb_substr(trim($input), 0, 500);
            }),
            'collect_currency' => tap($data, function (&$d) use ($input) {
                $currency = $this->parseCurrency($input);
                if ($currency) {
                    $d['currency'] = $currency;
                }
            }),
            'collect_amount' => tap($data, function (&$d) use ($input) {
                $amount = $this->parseAmount($input);
                if ($amount !== null) {
                    $d['amount'] = $amount;
                }
            }),
            default => $data,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyEventStep(string $step, array $data, string $input): array
    {
        return match ($step) {
            'collect_subject' => tap($data, function (&$d) use ($input) {
                $d = $this->mergeEventFreeform($d, $input);
                if (empty($d['subject'])) {
                    $d['subject'] = mb_substr(trim($input), 0, 200);
                }
            }),
            'collect_date' => tap($data, function (&$d) use ($input) {
                $date = NovaVoiceInputParser::parseDate($input);
                if ($date) {
                    $d['date'] = $date;
                }
                $time = NovaVoiceInputParser::parseTime($input);
                if ($time) {
                    $d['time'] = $time;
                }
            }),
            'collect_time' => tap($data, function (&$d) use ($input) {
                if ($this->isSkip($input)) {
                    $d['time'] = '';

                    return;
                }
                $time = NovaVoiceInputParser::parseTime($input);
                $d['time'] = $time ?? trim($input);
            }),
            'collect_message' => tap($data, function (&$d) use ($input) {
                if ($this->isSkip($input)) {
                    $d['message'] = '';

                    return;
                }
                $d['message'] = mb_substr(trim($input), 0, 2000);
            }),
            default => $data,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyQuoteStep(Client $client, string $step, array $data, string $input): array
    {
        return match ($step) {
            'collect_subject' => tap($data, function (&$d) use ($input) {
                $d['subject'] = mb_substr(trim($input), 0, 255);
            }),
            'collect_message' => tap($data, function (&$d) use ($input) {
                $d['message'] = mb_substr(trim($input), 0, 5000);
            }),
            'collect_phone' => tap($data, function (&$d) use ($input, $client) {
                if ($this->isSkip($input)) {
                    $d['phone'] = $client->phone ?? '';

                    return;
                }
                $d['phone'] = NovaVoiceInputParser::extractPhone($input) ?? trim($input);
            }),
            default => $data,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function invoiceMissing(array $data): array
    {
        $missing = [];
        if (empty($data['name'])) {
            $missing[] = 'collect_name';
        }
        if (empty($data['email'])) {
            $missing[] = 'collect_email';
        }
        if (! array_key_exists('phone', $data) || $data['phone'] === null) {
            $missing[] = 'collect_phone';
        }
        if (empty($data['description'])) {
            $missing[] = 'collect_description';
        }
        if (empty($data['currency'])) {
            $missing[] = 'collect_currency';
        }
        if (! isset($data['amount']) || $data['amount'] === '' || $data['amount'] === null) {
            $missing[] = 'collect_amount';
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function eventMissing(array $data): array
    {
        $missing = [];
        if (empty($data['subject'])) {
            $missing[] = 'collect_subject';
        }
        if (empty($data['date'])) {
            $missing[] = 'collect_date';
        }
        if (! array_key_exists('time', $data)) {
            $missing[] = 'collect_time';
        }
        if (! array_key_exists('message', $data)) {
            $missing[] = 'collect_message';
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function quoteMissing(array $data): array
    {
        $missing = [];
        if (empty($data['subject'])) {
            $missing[] = 'collect_subject';
        }
        if (empty($data['message'])) {
            $missing[] = 'collect_message';
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function invoicePrompt(string $step, array $data, Client $client): string
    {
        return match ($step) {
            'collect_name' => __('What is the client name?'),
            'collect_email' => __('What is the client email?'),
            'collect_phone' => __('What is the client phone number? Say skip if none.'),
            'collect_description' => __('What do you want to invoice for? Describe the product or service.'),
            'collect_currency' => __('Which currency? For example USD, EUR, or TND.'),
            'collect_amount' => __('What is the amount?'),
            default => __('Please continue.'),
        };
    }

    private function eventPrompt(string $step): string
    {
        return match ($step) {
            'collect_subject' => __('What is the event title?'),
            'collect_date' => __('What date? You can say today, tomorrow, or a date.'),
            'collect_time' => __('What time? Or say skip.'),
            'collect_message' => __('Any notes for this event? Or say skip.'),
            default => __('Please continue.'),
        };
    }

    private function quotePrompt(string $step): string
    {
        return match ($step) {
            'collect_subject' => __('What is the quote subject?'),
            'collect_message' => __('Tell me the details for your quote request.'),
            'collect_phone' => __('Phone number to include? Or say skip.'),
            default => __('Please continue.'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mergeInvoiceFreeform(array $data, string $input): array
    {
        $email = NovaVoiceInputParser::extractEmail($input);
        $phone = NovaVoiceInputParser::extractPhone($input);
        $currency = $this->parseCurrency($input);
        $amount = $this->parseAmount($input);
        if ($email) {
            $data['email'] = $email;
        }
        if ($phone) {
            $data['phone'] = $phone;
        }
        if ($currency) {
            $data['currency'] = $currency;
        }
        if ($amount !== null) {
            $data['amount'] = $amount;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mergeEventFreeform(array $data, string $input): array
    {
        $date = NovaVoiceInputParser::parseDate($input);
        $time = NovaVoiceInputParser::parseTime($input);
        if ($date) {
            $data['date'] = $date;
        }
        if ($time) {
            $data['time'] = $time;
        }

        $subject = trim(preg_replace(
            '/\b(create|book|schedule|event|meeting|rendez-vous|today|tomorrow|demain|aujourd\'hui|\d{1,2}[\/.\-]\d{1,2}(?:[\/.\-]\d{2,4})?)\b/iu',
            ' ',
            $input,
        ) ?? $input);
        $subject = trim(preg_replace('/\s+/u', ' ', $subject) ?? $subject);
        if ($subject !== '' && mb_strlen($subject) >= 3 && empty($data['subject'])) {
            $data['subject'] = mb_substr($subject, 0, 200);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mergeQuoteFreeform(array $data, string $input): array
    {
        $cleaned = trim(preg_replace('/\b(create|request|quote|devis|quotation)\b/iu', ' ', $input) ?? $input);
        $cleaned = trim(preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned);
        if ($cleaned !== '' && mb_strlen($cleaned) >= 3 && empty($data['subject'])) {
            $data['subject'] = mb_substr($cleaned, 0, 255);
        }

        return $data;
    }

    private function detectIntent(string $message): ?string
    {
        $n = mb_strtolower($message);

        if (preg_match('/\b(create|make|new|ajouter|créer|cree|demande)\b/u', $n) === 1
            && preg_match('/\b(invoice|facture|bill)\b/u', $n) === 1) {
            return 'create_invoice';
        }
        if (preg_match('/\b(create|book|schedule|make|new|créer|cree|planifier)\b/u', $n) === 1
            && preg_match('/\b(event|meeting|rendez-vous|rdv|appointment|calendar)\b/u', $n) === 1) {
            return 'create_event';
        }
        if (preg_match('/\b(create|request|make|new|créer|cree|demande)\b/u', $n) === 1
            && preg_match('/\b(quote|quotation|devis|proposal)\b/u', $n) === 1) {
            return 'create_quote';
        }
        if (preg_match('/\b(invoice|facture)\b/u', $n) === 1) {
            return 'create_invoice';
        }
        if (preg_match('/\b(event|meeting|rendez-vous|rdv)\b/u', $n) === 1) {
            return 'create_event';
        }
        if (preg_match('/\b(quote|devis)\b/u', $n) === 1) {
            return 'create_quote';
        }
        if (preg_match('/\b(create|make|new|créer|cree)\b/u', $n) === 1) {
            return 'choose';
        }

        return null;
    }

    private function parseCurrency(string $text): ?string
    {
        if (preg_match('/\b(USD|EUR|TND|GBP|MAD|CAD|AUD|CHF|JPY|SAR|AED)\b/i', $text, $m)) {
            return strtoupper($m[1]);
        }
        $n = mb_strtolower($text);
        $map = [
            'dollar' => 'USD', 'dollars' => 'USD', 'usd' => 'USD',
            'euro' => 'EUR', 'euros' => 'EUR', 'eur' => 'EUR',
            'dinar' => 'TND', 'dinars' => 'TND', 'tnd' => 'TND',
            'pound' => 'GBP', 'sterling' => 'GBP',
            'dirham' => 'MAD',
        ];
        foreach ($map as $word => $code) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $n)) {
                return $code;
            }
        }

        return null;
    }

    private function parseAmount(string $text): ?float
    {
        if (preg_match('/(\d+(?:[.,]\d{1,2})?)/u', $text, $m)) {
            $value = (float) str_replace(',', '.', $m[1]);

            return $value > 0 ? $value : null;
        }

        return null;
    }

    private function isCancel(string $message): bool
    {
        $n = mb_strtolower(trim($message));

        return preg_match('/\b(cancel|annuler|stop|abort|quit|never\s*mind)\b/u', $n) === 1;
    }

    private function isSkip(string $message): bool
    {
        $n = mb_strtolower(trim($message));

        return preg_match('/\b(skip|none|no|non|passer|rien)\b/u', $n) === 1;
    }

    /**
     * @return array{workflow: string, step: string, data: array<string, mixed>}|null
     */
    private function getSession(string $userId): ?array
    {
        $raw = Cache::get($this->key($userId));
        if (! is_array($raw) || empty($raw['workflow'])) {
            return null;
        }

        return [
            'workflow' => (string) $raw['workflow'],
            'step' => (string) ($raw['step'] ?? ''),
            'data' => is_array($raw['data'] ?? null) ? $raw['data'] : [],
        ];
    }

    /**
     * @param  array{workflow: string, step: string, data: array<string, mixed>}  $session
     */
    private function putSession(string $userId, array $session): void
    {
        Cache::put($this->key($userId), $session, self::TTL);
    }

    private function clearSession(string $userId): void
    {
        Cache::forget($this->key($userId));
    }

    /**
     * @return array{handled: bool, reply: string, done: bool, workflow: ?string, step: ?string}
     */
    private function clearAndFail(string $userId): array
    {
        $this->clearSession($userId);

        return [
            'handled' => true,
            'reply' => __('Something went wrong with that flow. Please try again.'),
            'done' => true,
            'workflow' => null,
            'step' => null,
        ];
    }

    private function key(string $userId): string
    {
        return 'client_nova_voice_wf:'.$userId;
    }
}
