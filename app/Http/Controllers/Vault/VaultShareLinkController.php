<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Mail\VaultShareLinkMail;
use App\Models\Company;
use App\Models\User;
use App\Models\VaultFile;
use App\Models\VaultFileShare;
use App\Services\AuditLogService;
use App\Services\TenantSmtpMailer;
use App\Services\Vault\VaultAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Liens de partage publics d'un fichier du Coffre-fort (espace entreprise). */
class VaultShareLinkController extends Controller
{
    public function __construct(protected VaultAccessService $access) {}

    protected function fileFor(User $user, string $id): VaultFile
    {
        abort_unless($this->access->isStaff($user), 403);
        $file = VaultFile::query()->withoutGlobalScopes()->where('company_id', $user->company_id)->whereKey($id)->firstOrFail();
        abort_unless($this->access->canEditSharing($user, $file), 403);

        return $file;
    }

    protected function shareFor(VaultFile $file, string $id): VaultFileShare
    {
        return VaultFileShare::query()->withoutGlobalScopes()
            ->where('company_id', $file->company_id)->where('vault_file_id', $file->id)->whereKey($id)->firstOrFail();
    }

    public function store(Request $request, string $file): RedirectResponse
    {
        $user = $request->user();
        $vault = $this->fileFor($user, $file);

        $data = $request->validate([
            'password' => ['nullable', 'string', 'min:4', 'max:100'],
            'expiry' => ['required', Rule::in(['1', '7', '30', '90', 'never', 'custom'])],
            'expires_on' => ['nullable', 'required_if:expiry,custom', 'date', 'after:today'],
            'max_downloads' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'allow_preview' => ['nullable', 'boolean'],
            'label' => ['nullable', 'string', 'max:120'],
        ], [], [
            'password' => __('vault_link_password'),
            'expires_on' => __('vault_link_expires_on'),
            'max_downloads' => __('vault_link_max_downloads'),
        ]);

        $expires = match ($data['expiry']) {
            'never' => null,
            'custom' => \Illuminate\Support\Carbon::parse($data['expires_on'])->endOfDay(),
            default => now()->addDays((int) $data['expiry']),
        };
        $plain = filled($data['password'] ?? null) ? (string) $data['password'] : null;

        $share = VaultFileShare::query()->withoutGlobalScopes()->create([
            'company_id' => $vault->company_id,
            'vault_file_id' => $vault->id,
            'token' => VaultFileShare::newToken(),
            'password_hash' => $plain !== null ? Hash::make($plain) : null,
            'expires_at' => $expires,
            'max_downloads' => $data['max_downloads'] ?? null,
            'allow_preview' => (bool) ($data['allow_preview'] ?? false),
            'label' => filled($data['label'] ?? null) ? trim($data['label']) : null,
            'created_by' => $user->id,
        ]);

        app(AuditLogService::class)->record('vault.share_link_created', $vault, [
            'name' => $vault->original_name,
            'share_id' => $share->id,
            'protected' => $plain !== null,
            'expires_at' => $expires?->toIso8601String(),
            'max_downloads' => $share->max_downloads,
        ], (string) $user->company_id, $user->id);

        // The plain password is flashed once so it can be shown/sent right away; it is never stored.
        return redirect()->route('vault.index', ['links' => $vault->id])
            ->with('vault_share_created', ['id' => $share->id, 'password' => $plain])
            ->with('status', __('vault_flash_link_created'));
    }

    public function destroy(Request $request, string $file, string $share): RedirectResponse
    {
        $user = $request->user();
        $vault = $this->fileFor($user, $file);
        $link = $this->shareFor($vault, $share);
        if (! $link->isRevoked()) {
            $link->forceFill(['revoked_at' => now()])->save();
            app(AuditLogService::class)->record('vault.share_link_revoked', $vault, ['name' => $vault->original_name, 'share_id' => $link->id], (string) $user->company_id, $user->id);
        }

        return redirect()->route('vault.index', ['links' => $vault->id])->with('status', __('vault_flash_link_revoked'));
    }

    public function email(Request $request, string $file, string $share): RedirectResponse
    {
        $user = $request->user();
        $vault = $this->fileFor($user, $file);
        $link = $this->shareFor($vault, $share);
        abort_unless($link->isActive(), 422);

        $data = $request->validate([
            'recipients' => ['required', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:2000'],
            'include_password' => ['nullable', 'boolean'],
            'link_password' => ['nullable', 'string', 'max:100'],
        ]);

        $emails = collect(preg_split('/[\s,;]+/', (string) $data['recipients']) ?: [])
            ->map(fn ($e) => strtolower(trim($e)))->filter()->unique()->values();
        $invalid = $emails->reject(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($emails->isEmpty() || $invalid->isNotEmpty() || $emails->count() > 10) {
            throw ValidationException::withMessages(['recipients' => __('vault_send_invalid_recipients')]);
        }

        $password = null;
        if ($link->hasPassword() && ($data['include_password'] ?? false)) {
            $password = (string) ($data['link_password'] ?? '');
            if ($password === '' || ! Hash::check($password, (string) $link->password_hash)) {
                throw ValidationException::withMessages(['link_password' => __('vault_send_wrong_password')]);
            }
        }

        $company = Company::query()->findOrFail($user->company_id);
        $url = $link->publicUrl();
        $sent = 0;
        $mailer = app(TenantSmtpMailer::class);
        foreach ($emails as $email) {
            try {
                $mailer->send($company, new VaultShareLinkMail(
                    $link, $vault, $company, $url, $password,
                    filled($data['message'] ?? null) ? trim((string) $data['message']) : null,
                    (string) $user->name,
                ), $email);
                $sent++;
            } catch (Throwable $e) {
                Log::warning('vault.share_email_failed', ['share' => $link->id, 'error' => $e->getMessage()]);
            }
        }

        app(AuditLogService::class)->record('vault.share_link_emailed', $vault, [
            'name' => $vault->original_name,
            'share_id' => $link->id,
            'recipients' => $sent,
            'password_included' => $password !== null,
        ], (string) $user->company_id, $user->id);

        $redirect = redirect()->route('vault.index', ['links' => $vault->id]);

        return $sent > 0
            ? $redirect->with('status', trans_choice('vault_flash_link_emailed', $sent, ['count' => $sent]))
            : $redirect->withErrors(['recipients' => __('vault_send_failed')]);
    }
}
