<?php

namespace App\Services\Vault;

use App\Models\User;
use App\Models\VaultFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stockage privé du Coffre-fort (disque « local » = storage/app/private, jamais public). */
class VaultStorageService
{
    public const DISK = 'local';

    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt',
        'xls', 'xlsx', 'ods', 'csv',
        'ppt', 'pptx', 'odp',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'zip',
    ];

    /** @var list<string> */
    public const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps',
        'exe', 'com', 'bat', 'cmd', 'msi', 'dll', 'scr', 'vbs', 'vbe', 'ps1', 'psm1',
        'sh', 'bash', 'zsh', 'csh', 'ksh', 'cgi', 'pl', 'py', 'rb', 'jar', 'war',
        'js', 'mjs', 'cjs', 'jsx', 'ts', 'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'xml',
        'asp', 'aspx', 'jsp', 'htaccess', 'htpasswd', 'ini', 'env', 'swf', 'hta', 'lnk', 'app', 'dmg', 'apk',
    ];

    public function maxFileKb(): int
    {
        return max(1, (int) config('flowdesk.project_files.max_file_kb', 12288));
    }

    /** @return list<string|\Closure> */
    public function fileRules(): array
    {
        return [
            'required',
            'file',
            'max:'.$this->maxFileKb(),
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    $fail(__('vault_error_file'));

                    return;
                }
                if ($reason = $this->rejectionReason($value)) {
                    $fail($reason);
                }
            },
        ];
    }

    /** Null when the file is acceptable, otherwise a translated reason. */
    public function rejectionReason(UploadedFile $file): ?string
    {
        $name = strtolower(trim((string) $file->getClientOriginalName()));
        $parts = array_values(array_filter(explode('.', $name), fn ($p) => $p !== ''));
        $ext = count($parts) > 1 ? (string) end($parts) : '';

        foreach (array_slice($parts, 1) as $segment) {
            if (in_array($segment, self::BLOCKED_EXTENSIONS, true)) {
                return __('vault_error_blocked', ['ext' => $segment]);
            }
        }
        if ($ext === '' || ! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return __('vault_error_type', ['types' => implode(', ', self::ALLOWED_EXTENSIONS)]);
        }
        $guessed = strtolower((string) $file->guessExtension());
        if (in_array($guessed, self::BLOCKED_EXTENSIONS, true)) {
            return __('vault_error_blocked', ['ext' => $guessed]);
        }
        $mime = strtolower((string) $file->getMimeType());
        if (str_contains($mime, 'php') || str_contains($mime, 'x-sh') || str_contains($mime, 'javascript')
            || str_contains($mime, 'html') || str_contains($mime, 'svg') || str_contains($mime, 'x-msdownload')
            || str_contains($mime, 'x-executable') || str_contains($mime, 'x-dosexec')) {
            return __('vault_error_blocked', ['ext' => $mime]);
        }

        return null;
    }

    /** @param array<string, mixed> $attributes */
    public function store(UploadedFile $upload, User $user, array $attributes): VaultFile
    {
        $ext = strtolower((string) $upload->getClientOriginalExtension());
        $dir = 'vault/companies/'.$user->company_id.'/'.now()->format('Y/m');
        $path = $upload->storeAs($dir, Str::ulid()->toBase32().'.'.$ext, self::DISK);
        $original = Str::limit(trim(str_replace(['/', '\\', "\0"], '-', (string) $upload->getClientOriginalName())), 240, '');

        return VaultFile::query()->withoutGlobalScopes()->create(array_merge([
            'company_id' => $user->company_id,
            'uploaded_by' => $user->id,
            'original_name' => $original !== '' ? $original : ('fichier.'.$ext),
            'extension' => $ext,
            'disk' => self::DISK,
            'path' => $path,
            'mime' => $upload->getMimeType(),
            'size' => (int) $upload->getSize(),
        ], $attributes));
    }

    public function download(VaultFile $file): StreamedResponse
    {
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function preview(VaultFile $file): StreamedResponse
    {
        abort_unless($file->isPreviewable(), 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);
        $mime = $file->isPdf() ? 'application/pdf' : match (strtolower((string) $file->extension)) {
            'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', default => 'image/jpeg',
        };
        $safeName = Str::ascii($file->original_name) ?: 'fichier';

        return Storage::disk($file->disk)->response($file->path, $safeName, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addcslashes($safeName, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; plugin-types application/pdf; sandbox allow-same-origin",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
