<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\CompanyNamingService;
use App\Services\SubscriptionBootstrapService;
use App\Services\TenantStorageService;
use App\Services\AdminCompanyWelcomeService;
use App\Services\WorkspacePlanActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class CompanyController extends Controller
{
    use \App\Http\Controllers\Concerns\RetriesCompanyCreation;

    public function index(Request $request): View
    {
        $subscriptionFilter = $request->query('subscription', 'all');
        if (! in_array($subscriptionFilter, ['all', 'active', 'ended'], true)) {
            $subscriptionFilter = 'all';
        }

        $companies = Company::query()
            ->with([
                'plan',
                'subscriptions' => fn ($q) => $q->withoutGlobalScopes()->with('plan')->latest('id'),
            ])
            ->withCount('users')
            ->when($subscriptionFilter === 'ended', function ($query) {
                $query->whereHas('subscriptions', function ($sq) {
                    $sq->withoutGlobalScopes()
                        ->where(function ($inner) {
                            $inner->whereIn('status', ['canceled', 'cancelled', 'expired'])
                                ->orWhere(function ($q) {
                                    $q->where('status', 'active')
                                        ->whereNotNull('current_period_end')
                                        ->where('current_period_end', '<', now());
                                })
                                ->orWhere(function ($q) {
                                    $q->where('status', 'trialing')
                                        ->whereNotNull('trial_ends_at')
                                        ->where('trial_ends_at', '<', now());
                                });
                        });
                });
            })
            ->when($subscriptionFilter === 'active', function ($query) {
                $query->whereHas('subscriptions', function ($sq) {
                    $sq->withoutGlobalScopes()
                        ->where(function ($inner) {
                            $inner->where(function ($q) {
                                $q->where('status', 'active')
                                    ->where(function ($period) {
                                        $period->whereNull('current_period_end')
                                            ->orWhere('current_period_end', '>=', now());
                                    });
                            })->orWhere(function ($q) {
                                $q->where('status', 'trialing')
                                    ->where(function ($trial) {
                                        $trial->whereNull('trial_ends_at')
                                            ->orWhere('trial_ends_at', '>=', now());
                                    });
                            });
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.companies.index', compact('companies', 'subscriptionFilter'));
    }

    public function create(CompanyNamingService $naming): View
    {
        $plans = Plan::query()->orderBy('name')->get();
        $locales = config('flowdesk.locales', ['fr', 'en', 'es', 'ar']);
        $currencies = config('flowdesk.supported_currencies', ['EUR']);
        $currencyLabels = config('flowdesk.currency_labels', []);
        $countries = config('flowdesk_countries', []);
        $suggestedSubdomain = $naming->uniqueSubdomain('nouvelle-societe');
        $generatedPassword = Str::password(12, symbols: false);

        return view('admin.companies.create', compact(
            'plans', 'locales', 'currencies', 'currencyLabels', 'countries', 'suggestedSubdomain', 'generatedPassword'
        ));
    }

    public function store(Request $request, AdminCompanyWelcomeService $welcome): RedirectResponse
    {
        $generatePassword = $request->boolean('generate_password');
        $passwordRules = $generatePassword
            ? ['nullable', 'string', 'min:8', 'max:255']
            : ['required', 'string', 'min:8', 'max:255', PasswordRule::defaults()];

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'subdomain' => [
                'required',
                'string',
                'max:48',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('companies', 'subdomain'),
                Rule::unique('companies', 'slug'),
            ],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'country' => ['nullable', 'string', 'size:2', Rule::in(array_keys(config('flowdesk_countries', [])))],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'default_locale' => ['required', 'string', Rule::in(config('flowdesk.locales', ['fr', 'en', 'es', 'ar']))],
            'default_currency' => ['required', 'string', Rule::in(config('flowdesk.supported_currencies', ['EUR']))],
            'owner_first_name' => ['required', 'string', 'max:120'],
            'owner_last_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => $passwordRules,
            'generate_password' => ['sometimes', 'boolean'],
            'include_password' => ['sometimes', 'boolean'],
            'plan_id' => ['nullable', Rule::exists('plans', 'id')],
            'plan_locked' => ['sometimes', 'boolean'],
            'subscription_mode' => ['required', Rule::in(['trial', 'active'])],
        ]);

        $plainPassword = $generatePassword
            ? (filled($data['admin_password'] ?? null) ? (string) $data['admin_password'] : Str::password(12, symbols: false))
            : (string) $data['admin_password'];
        $includePassword = $request->boolean('include_password');
        $ownerName = trim($data['owner_first_name'].' '.$data['owner_last_name']);
        $country = ! empty($data['country']) ? strtoupper((string) $data['country']) : 'FR';
        $contactEmail = $data['contact_email'] ?? $data['admin_email'];

        $naming = app(CompanyNamingService::class);
        $tenantStorage = app(TenantStorageService::class);
        $bootstrap = app(SubscriptionBootstrapService::class);
        $activation = app(WorkspacePlanActivationService::class);

        $created = $this->createCompanyWithRetry($request, function () use ($data, $naming, $tenantStorage, $bootstrap, $activation, $plainPassword, $ownerName, $country, $contactEmail) {
            $subdomain = $data['subdomain'];
            if ($naming /* uniqueness already validated; still resolve slug */) {
                $slug = $subdomain;
                if (\App\Models\Company::withTrashed()->where('slug', $slug)->where('subdomain', '!=', $subdomain)->exists()) {
                    $slug = $naming->uniqueSlug($data['company_name']);
                }
            }

            $company = Company::query()->create([
                'name' => $data['company_name'],
                'contact_email' => $contactEmail,
                'phone' => $data['phone'] ?? null,
                'country' => $country,
                'tax_id' => $data['tax_id'] ?? null,
                'subdomain' => $subdomain,
                'slug' => $slug,
                'default_locale' => $data['default_locale'],
                'default_currency' => $data['default_currency'],
            ]);

            $tenantStorage->bootstrap($company);

            $plan = ! empty($data['plan_id']) ? Plan::query()->find($data['plan_id']) : null;
            if (($data['subscription_mode'] ?? 'trial') === 'active' && $plan) {
                $activation->activate($company, $plan, 1, force: true);
            } else {
                $bootstrap->ensureDefaultSubscription($company);
                if ($plan) {
                    $company->forceFill(['plan_id' => $plan->id])->saveQuietly();
                    $sub = $company->subscriptions()->withoutGlobalScopes()->latest('id')->first();
                    if ($sub) {
                        $sub->forceFill(['plan_id' => $plan->id])->saveQuietly();
                    }
                }
            }

            $company->forceFill([
                'plan_id' => $plan?->id ?? $company->plan_id,
                'plan_locked' => (bool) ($data['plan_locked'] ?? false),
            ])->saveQuietly();

            $user = User::query()->create([
                'name' => $ownerName,
                'email' => $data['admin_email'],
                'password' => Hash::make($plainPassword),
                'company_id' => $company->id,
                'locale' => $company->default_locale,
                'default_currency' => $company->default_currency,
            ]);
            // email_verified_at is not mass-assignable on User.
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole('company_admin');

            return [$company, $user];
        }, 3, 'subdomain');
        if ($created instanceof \Illuminate\Http\RedirectResponse) {
            return $created;
        }
        [$company, $user] = $created;

        $mail = $welcome->send(
            $company,
            $user,
            $includePassword ? $plainPassword : null,
        );

        $flash = [
            'status' => __('admin_company_created_flash', ['company' => $company->name]),
            'welcome_email' => $user->email,
            'welcome_url' => $welcome->loginUrl($company),
            'welcome_password' => $generatePassword ? $plainPassword : null,
        ];
        if (! $mail['sent']) {
            $flash['welcome_mail_warning'] = __('admin_welcome_mail_failed', ['error' => $mail['error'] ?: __('Unknown error')]);
        }

        return redirect()->route('admin.companies.show', $company)->with($flash);
    }

    public function resendWelcome(Company $company, AdminCompanyWelcomeService $welcome): RedirectResponse
    {
        $owner = User::query()
            ->where('company_id', $company->id)
            ->role('company_admin')
            ->orderBy('id')
            ->first();

        if (! $owner) {
            return redirect()->route('admin.companies.show', $company)
                ->with('welcome_mail_warning', __('admin_welcome_mail_no_owner'));
        }

        $mail = $welcome->send($company, $owner, null);

        if (! $mail['sent']) {
            return redirect()->route('admin.companies.show', $company)
                ->with('welcome_mail_warning', __('admin_welcome_mail_failed', ['error' => $mail['error'] ?: __('Unknown error')]));
        }

        return redirect()->route('admin.companies.show', $company)
            ->with('status', __('admin_welcome_mail_resent', ['email' => $owner->email]));
    }

    public function show(Company $company): View
    {
        $company->load(['plan']);
        $company->loadCount(['users', 'projects', 'clients', 'invoices', 'proposals']);
        $subscriptions = $company->subscriptions()->withoutGlobalScopes()->with('plan')->latest()->get();
        $plans = Plan::query()->with('periodPrices')->orderBy('name')->get();
        $periodOptions = [1, 3, 6, 12];
        $activeSubscription = $subscriptions->first(fn ($s) => in_array($s->status, ['active', 'trialing'], true));
        $currentPeriodMonths = (int) ($activeSubscription?->period_months ?? 0);

        return view('admin.companies.show', compact(
            'company',
            'subscriptions',
            'plans',
            'periodOptions',
            'activeSubscription',
            'currentPeriodMonths',
        ));
    }

    public function destroy(Request $request, Company $company, \App\Services\CompanyPurgeService $purge): RedirectResponse
    {
        $data = $request->validate([
            'confirm' => ['required', 'string'],
            'send_email' => ['sometimes', 'boolean'],
        ]);

        abort_if(trim($data['confirm']) !== $company->subdomain, 422);

        $notifyEmail = $company->contact_email;
        if (! $notifyEmail) {
            $notifyEmail = User::query()
                ->where('company_id', $company->id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'company_admin'))
                ->value('email');
        }

        // Permanently delete the workspace with its users, projects and all tenant data + files.
        $stats = $purge->purge($company);

        if ($request->boolean('send_email') && $notifyEmail) {
            $lines = [
                'Your ClicFlow workspace has been removed by the platform administrator.',
                '',
                'Workspace: '.$company->name,
                'Subdomain: '.$company->subdomain,
            ];
            try {
                Mail::raw(implode("\n", $lines), function ($m) use ($notifyEmail, $company): void {
                    $m->to($notifyEmail)->subject('Workspace removed: '.$company->name);
                });
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return redirect()->route('admin.companies.index')->with('status', __('Company removed with :users user(s), :projects project(s) and all related data.', ['users' => $stats['users'], 'projects' => $stats['projects']]));
    }

    public function updateStatus(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'disabled_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $enabled = (bool) $data['is_enabled'];

        $company->forceFill([
            'is_enabled' => $enabled,
            'disabled_at' => $enabled ? null : now(),
            'disabled_reason' => $enabled ? null : ($data['disabled_reason'] ?? null),
        ])->save();

        return redirect()->route('admin.companies.show', $company)->with('status', __('Company status updated.'));
    }

    public function updateNovaVoice(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate(['admin_nova_voice_enabled' => ['required', 'boolean']]);
        $company->forceFill(['admin_nova_voice_enabled' => (bool) $data['admin_nova_voice_enabled']])->save();

        return redirect()->route('admin.companies.show', $company)
            ->with('status', __($data['admin_nova_voice_enabled'] ? 'admin_nova_voice_enabled_saved' : 'admin_nova_voice_disabled_saved'));
    }

    public function updatePlan(Request $request, Company $company, WorkspacePlanActivationService $activation): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['nullable', Rule::exists('plans', 'id')],
            'plan_locked' => ['required', 'boolean'],
            'period_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'apply_period' => ['sometimes', 'boolean'],
        ]);

        $planLocked = (bool) $data['plan_locked'];
        $planId = $data['plan_id'] ?? null;
        $applyPeriod = $request->boolean('apply_period') || filled($data['period_months'] ?? null);

        $company->forceFill([
            'plan_id' => $planId,
            'plan_locked' => $planLocked,
        ])->save();

        if ($applyPeriod && $planId && filled($data['period_months'] ?? null)) {
            $plan = Plan::query()->findOrFail($planId);
            $subscription = $activation->activate($company, $plan, (int) $data['period_months'], force: true);
            $company->forceFill([
                'plan_id' => $plan->id,
                'plan_locked' => $planLocked,
            ])->saveQuietly();

            $end = $subscription->current_period_end?->format('Y-m-d') ?? '—';

            return redirect()
                ->route('admin.companies.show', $company)
                ->with('status', __('Company plan renewed: :months months, period ends :end. Open :host/billing to verify.', [
                    'months' => (int) $data['period_months'],
                    'end' => $end,
                    'host' => $company->subdomain,
                ]));
        }

        return redirect()->route('admin.companies.show', $company)->with('status', __('Company plan updated.'));
    }
}
