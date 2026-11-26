<?php

namespace App\Services;

class ContractSampleService
{
    /** @return list<string> */
    public function locales(): array
    {
        return ['en', 'fr', 'es', 'ar'];
    }

    /** @return list<string> */
    public function typeKeys(): array
    {
        return ['service', 'nda', 'retainer', 'consulting'];
    }

    public function html(string $typeKey, string $locale): string
    {
        if (! in_array($typeKey, $this->typeKeys(), true)) {
            $typeKey = 'service';
        }
        if (! in_array($locale, $this->locales(), true)) {
            $locale = 'en';
        }

        $raw = (string) __('contract_sample_'.$typeKey, [], $locale);

        return $this->toHtml($raw);
    }

    private function toHtml(string $raw): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [$raw];
        $html = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '## ')) {
                $html .= '<h3>'.e(substr($line, 3)).'</h3>';

                continue;
            }
            if (str_starts_with($line, '# ')) {
                $html .= '<h2>'.e(substr($line, 2)).'</h2>';

                continue;
            }

            $html .= '<p>'.$this->escapeKeepingMergeTags($line).'</p>';
        }

        return $html !== '' ? $html : '<p></p>';
    }

    private function escapeKeepingMergeTags(string $line): string
    {
        $tokens = [];
        $masked = preg_replace_callback('/\{\{[a-zA-Z0-9_]+\}\}/', function (array $m) use (&$tokens): string {
            $key = '%%MERGE'.count($tokens).'%%';
            $tokens[$key] = $m[0];

            return $key;
        }, $line) ?? $line;

        $safe = e($masked);
        foreach ($tokens as $key => $token) {
            $safe = str_replace($key, $token, $safe);
        }

        return $safe;
    }
}
