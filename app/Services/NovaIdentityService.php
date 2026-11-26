<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class NovaIdentityService
{
    /**
     * @return list<string>
     */
    public function phrasePatterns(?string $locale = null): array
    {
        $locale = $locale !== null && $locale !== '' ? $locale : app()->getLocale();
        $base = strtolower(substr($locale, 0, 2));

        $patterns = [
            'who are you',
            'what are you',
            'who is nova',
            'who is clicflow',
            'introduce yourself',
            'present yourself',
            'tell me about yourself',
            'what can you do',
            'what do you do',
            'what are your capabilities',
            'how can you help',
            'how do you work',
            // Always include common FR forms (speech often mixes locales).
            'qui es tu',
            'qui es-tu',
            'qui etes vous',
            'qui êtes-vous',
            'presente toi',
            'présente toi',
            'présente-toi',
            'que peux tu faire',
            'que peux-tu faire',
            'que sais tu faire',
            'a quoi tu sers',
            'à quoi tu sers',
            'parle moi de toi',
            'parle-moi de toi',
            // Always include common AR forms (help examples / mixed speech locales).
            'من انت',
            'من أنت',
            'من انا',
            'من أنا',
            'عرف بنفسك',
            'عرّف بنفسك',
            'عرفي بنفسك',
            'من هي نوفا',
            'من هي nova',
            'ماذا يمكنك ان تفعل',
            'ماذا يمكنك أن تفعل',
            'ماذا تستطيع',
            'ماذا تقدر ان تفعل',
            'ايش تقدر تسوي',
            'وش تقدر تسوي',
        ];

        if ($base === 'fr') {
            // already merged above
        } elseif ($base === 'es') {
            $patterns = array_merge($patterns, [
                'quien eres',
                'quién eres',
                'presentate',
                'preséntate',
                'que puedes hacer',
                'qué puedes hacer',
                'hablame de ti',
                'háblame de ti',
            ]);
        } elseif ($base === 'ar') {
            // already merged above
        } elseif ($base === 'hi') {
            $patterns = array_merge($patterns, [
                'tum kaun ho',
                'aap kaun hain',
                'apna parichay',
                'tum kya kar sakte ho',
            ]);
        } elseif ($base === 'id') {
            $patterns = array_merge($patterns, [
                'siapa kamu',
                'perkenalkan diri',
                'apa yang bisa kamu lakukan',
            ]);
        }

        return array_values(array_unique($patterns));
    }

    public function isIdentityQuestion(string $message): bool
    {
        $normalized = $this->normalize($message);
        if ($normalized === '') {
            return false;
        }

        foreach ($this->phrasePatterns() as $pattern) {
            $needle = $this->normalize($pattern);
            if ($needle !== '' && str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function reply(Company $company, User $user): string
    {
        $brand = (string) config('flowdesk.ai_assistant_brand_name', 'Nova');
        $firstName = trim(explode(' ', trim((string) $user->name), 2)[0]);
        $companyName = trim((string) ($company->name ?? config('app.name')));

        return __('nova_voice_identity_reply', [
            'name' => $brand,
            'user' => $firstName !== '' ? $firstName : __('nova_voice_guest'),
            'company' => $companyName !== '' ? $companyName : config('app.name'),
        ]);
    }

    private function normalize(string $text): string
    {
        $s = mb_strtolower(trim($text));
        $s = str_replace(['’', '`'], "'", $s);

        // Fold Arabic letter variants so «من أنت؟» matches «من انت».
        if (preg_match('/\p{Arabic}/u', $s)) {
            $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $s) ?? $s;
            $s = str_replace("\u{0640}", '', $s); // tatweel
            $s = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $s);
            $s = str_replace(['ى'], 'ي', $s);
            $s = str_replace(['ة'], 'ه', $s);
            $s = str_replace(['ؤ'], 'و', $s);
            $s = str_replace(['ئ'], 'ي', $s);
        }

        $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }
}
