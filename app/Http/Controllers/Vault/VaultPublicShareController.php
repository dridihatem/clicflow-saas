<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\VaultFileShare;
use App\Services\AuditLogService;
use App\Services\Vault\VaultStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Page publique d'un lien de partage du Coffre-fort (avec ou sans mot de passe). */
class VaultPublicShareController extends Controller
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 600;

    public function __construct(protected VaultStorageService $storage) {}

    /** Null when the link is unusable (unknown, revoked, expired, exhausted, deleted file). */
    protected function resolve(string $token): ?VaultFileShare
    {
        if (! preg_match('/^[A-Za-z0-9]{40,80}$/', $token)) {
            return null;
        }
        $share = VaultFileShare::query()->withoutGlobalScopes()->where('token', $token)
            ->with(['file' => fn ($q) => $q->withoutGlobalScopes()->withTrashed(), 'company'])->first();
        if (! $share || ! $share->isActive() || ! $share->file || $share->file->trashed()
            || (string) $share->file->company_id !== (string) $share->company_id
            || ! Storage::disk($share->file->disk)->exists($share->file->path)) {
            return null;
        }

        return $share;
    }

    protected function unavailable(): Response
    {
        return response()->view('vault-share.show', ['state' => 'unavailable', 'share' => null, 'file' => null], 404);
    }

    protected function sessionKey(VaultFileShare $share): string
    {
        return 'vault_share_unlocked.'.$share->id;
    }

    protected function unlocked(Request $request, VaultFileShare $share): bool
    {
        return ! $share->hasPassword() || $request->session()->get($this->sessionKey($share)) === hash('sha256', (string) $share->password_hash);
    }

    protected function limiterKey(Request $request, VaultFileShare $share): string
    {
        return 'vault-share-pw:'.$share->id.'|'.$request->ip();
    }

    protected function audit(VaultFileShare $share, string $action, array $extra = []): void
    {
        app(AuditLogService::class)->record($action, $share->file, array_merge([
            'name' => $share->file?->original_name,
            'share_id' => $share->id,
            'ip' => request()->ip(),
        ], $extra), (string) $share->company_id, null);
    }

    public function show(Request $request, string $token): Response
    {
        $share = $this->resolve($token);
        if (! $share) {
            return $this->unavailable();
        }
        if (! $this->unlocked($request, $share)) {
            $key = $this->limiterKey($request, $share);

            return response()->view('vault-share.show', [
                'state' => 'password',
                'share' => $share,
                'file' => $share->file,
                'lockedFor' => RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS) ? RateLimiter::availableIn($key) : 0,
            ])->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $seenKey = 'vault_share_seen.'.$share->id;
        if (! $request->session()->has($seenKey)) {
            $request->session()->put($seenKey, true);
            $share->forceFill(['view_count' => $share->view_count + 1, 'last_accessed_at' => now()])->save();
            $this->audit($share, 'vault.share_link_viewed');
        }

        return response()->view('vault-share.show', [
            'state' => 'ready',
            'share' => $share,
            'file' => $share->file,
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function unlock(Request $request, string $token): RedirectResponse|Response
    {
        $share = $this->resolve($token);
        if (! $share) {
            return $this->unavailable();
        }
        if (! $share->hasPassword()) {
            return redirect()->route('vault-share.show', $token);
        }
        $key = $this->limiterKey($request, $share);
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return redirect()->route('vault-share.show', $token)
                ->withErrors(['password' => __('vault_public_too_many', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)])]);
        }
        $request->validate(['password' => ['required', 'string', 'max:200']]);
        if (! Hash::check((string) $request->input('password'), (string) $share->password_hash)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $this->audit($share, 'vault.share_link_password_failed');

            return redirect()->route('vault-share.show', $token)->withErrors(['password' => __('vault_public_wrong_password')]);
        }
        RateLimiter::clear($key);
        $request->session()->put($this->sessionKey($share), hash('sha256', (string) $share->password_hash));
        $request->session()->regenerate();

        return redirect()->route('vault-share.show', $token);
    }

    public function download(Request $request, string $token): StreamedResponse|Response|RedirectResponse
    {
        $share = $this->resolve($token);
        if (! $share) {
            return $this->unavailable();
        }
        if (! $this->unlocked($request, $share)) {
            return redirect()->route('vault-share.show', $token);
        }
        // Atomic counter that respects max_downloads even under concurrent requests.
        $q = DB::table('vault_file_shares')->where('id', $share->id)->whereNull('revoked_at');
        if ($share->max_downloads !== null) {
            $q->where('download_count', '<', $share->max_downloads);
        }
        if ($q->update(['download_count' => DB::raw('download_count + 1'), 'last_accessed_at' => now()]) === 0) {
            return $this->unavailable();
        }
        $this->audit($share, 'vault.share_link_downloaded');

        return $this->storage->download($share->file);
    }

    public function preview(Request $request, string $token): StreamedResponse|Response|RedirectResponse
    {
        $share = $this->resolve($token);
        if (! $share || ! $share->allow_preview || ! $share->file->isPreviewable()) {
            return $this->unavailable();
        }
        if (! $this->unlocked($request, $share)) {
            return redirect()->route('vault-share.show', $token);
        }

        return $this->storage->preview($share->file);
    }
}
