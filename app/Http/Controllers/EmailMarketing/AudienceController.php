<?php

namespace App\Http\Controllers\EmailMarketing;

use App\Enums\ProspectStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EmailMarketingAudience;
use App\Models\Prospect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AudienceController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->company, 403);

        $audiences = EmailMarketingAudience::query()
            ->withCount('contacts')
            ->orderBy('name')
            ->paginate(15);

        return view('email-marketing.audiences.index', compact('audiences'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->company, 403);

        return view('email-marketing.audiences.create', [
            'clientEmails' => $this->clientEmails(),
            'prospectEmails' => $this->prospectEmails(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contacts_input' => ['nullable', 'string', 'max:65535'],
        ]);

        $emails = $this->parseContactEmails($data['contacts_input'] ?? '');

        $audience = EmailMarketingAudience::query()->create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $this->syncContacts($audience, $emails, $company->id);

        return redirect()
            ->route('email-marketing.audiences.index')
            ->with('status', __('email_marketing_audience_saved'));
    }

    public function edit(Request $request, EmailMarketingAudience $audience): View
    {
        abort_if(! $request->user()->company, 403);
        $this->authorizeAudience($request, $audience);

        $contactsInput = $audience->contacts()->orderBy('email')->pluck('email')->implode("\n");
        $clientEmails = $this->clientEmails();
        $prospectEmails = $this->prospectEmails();

        return view('email-marketing.audiences.edit', compact('audience', 'contactsInput', 'clientEmails', 'prospectEmails'));
    }

    public function update(Request $request, EmailMarketingAudience $audience): RedirectResponse
    {
        abort_if(! $request->user()->company, 403);
        $this->authorizeAudience($request, $audience);
        $company = $request->user()->company;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contacts_input' => ['nullable', 'string', 'max:65535'],
        ]);

        $emails = $this->parseContactEmails($data['contacts_input'] ?? '');

        $audience->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $audience->contacts()->delete();
        $this->syncContacts($audience, $emails, $company->id);

        return redirect()
            ->route('email-marketing.audiences.index')
            ->with('status', __('email_marketing_audience_saved'));
    }

    public function destroy(Request $request, EmailMarketingAudience $audience): RedirectResponse
    {
        abort_if(! $request->user()->company, 403);
        $this->authorizeAudience($request, $audience);

        $audience->delete();

        return redirect()
            ->route('email-marketing.audiences.index')
            ->with('status', __('email_marketing_audience_deleted'));
    }

    private function authorizeAudience(Request $request, EmailMarketingAudience $audience): void
    {
        $companyId = $request->user()?->company_id;
        abort_if(! $companyId || (string) $audience->company_id !== (string) $companyId, 403);
    }

    /**
     * @param  list<string>  $emails
     */
    private function syncContacts(EmailMarketingAudience $audience, array $emails, string $companyId): void
    {
        $prospectMap = Prospect::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('email', $emails)
            ->whereNotNull('email')
            ->pluck('id', 'email');

        foreach ($emails as $email) {
            $audience->contacts()->create([
                'email' => $email,
                'prospect_id' => $prospectMap[$email] ?? null,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function clientEmails(): array
    {
        $companyId = auth()->user()?->company_id;
        if (! $companyId) {
            return [];
        }

        return Client::query()
            ->where('company_id', $companyId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('email')
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function prospectEmails(): array
    {
        $companyId = auth()->user()?->company_id;
        if (! $companyId) {
            return [];
        }

        return Prospect::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotIn('status', [ProspectStatus::Converted->value, ProspectStatus::Archived->value])
            ->orderBy('email')
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function parseContactEmails(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $emails = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (! filter_var($line, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'contacts_input' => [__('email_marketing_audience_invalid_email', ['line' => $line])],
                ]);
            }

            $emails[strtolower($line)] = strtolower($line);
        }

        return array_values($emails);
    }
}
