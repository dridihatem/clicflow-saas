<?php

namespace App\Services\AiSidebar;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Small deterministic parser for French (and basic English) date expressions used by the AI chat:
 * aujourd'hui, demain, après-demain, vendredi, lundi prochain, dans 3 jours, dans 2 semaines,
 * fin du mois, fin de semaine, semaine prochaine, mois prochain, le 30 décembre, 1er mars 2027, 30/12, 30/12/2026, 2026-12-30.
 */
class AiDateParser
{
    private const MONTHS = [
        'janvier' => 1, 'janv' => 1, 'january' => 1, 'jan' => 1,
        'fevrier' => 2, 'fevr' => 2, 'fev' => 2, 'february' => 2, 'feb' => 2,
        'mars' => 3, 'march' => 3, 'mar' => 3,
        'avril' => 4, 'avr' => 4, 'april' => 4, 'apr' => 4,
        'mai' => 5, 'may' => 5,
        'juin' => 6, 'june' => 6, 'jun' => 6,
        'juillet' => 7, 'juil' => 7, 'july' => 7, 'jul' => 7,
        'aout' => 8, 'august' => 8, 'aug' => 8,
        'septembre' => 9, 'sept' => 9, 'september' => 9, 'sep' => 9,
        'octobre' => 10, 'oct' => 10, 'october' => 10,
        'novembre' => 11, 'nov' => 11, 'november' => 11,
        'decembre' => 12, 'dec' => 12, 'december' => 12,
    ];

    private const WEEKDAYS = [
        'lundi' => 1, 'monday' => 1, 'mardi' => 2, 'tuesday' => 2, 'mercredi' => 3, 'wednesday' => 3,
        'jeudi' => 4, 'thursday' => 4, 'vendredi' => 5, 'friday' => 5, 'samedi' => 6, 'saturday' => 6,
        'dimanche' => 7, 'sunday' => 7,
    ];

    private const NUMBERS = ['un' => 1, 'une' => 1, 'deux' => 2, 'trois' => 3, 'quatre' => 4, 'cinq' => 5, 'six' => 6, 'sept' => 7, 'huit' => 8, 'neuf' => 9, 'dix' => 10, 'quinze' => 15, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'ten' => 10];

    /** First date expression found in $text as Y-m-d, or null. */
    public function parse(string $text, ?CarbonInterface $today = null): ?string
    {
        return $this->extract($text, $today)['date'] ?? null;
    }

    /** @return array{date: string, match: string}|null */
    public function extract(string $text, ?CarbonInterface $today = null): ?array
    {
        $today = CarbonImmutable::instance($today ?? now())->startOfDay();
        // Accent-free lowercase copy; same length mapping is not needed because we only return the date.
        $t = ' '.Str::lower(Str::ascii($text)).' ';
        $t = str_replace(['’', "'"], "'", $t);
        $months = implode('|', array_keys(self::MONTHS));
        $days = implode('|', array_keys(self::WEEKDAYS));
        $nums = implode('|', array_keys(self::NUMBERS));

        $candidates = [];
        $try = function (string $re, callable $fn) use ($t, &$candidates): void {
            if (preg_match($re, $t, $m, PREG_OFFSET_CAPTURE)) {
                $d = $fn(array_map(fn ($x) => $x[0], $m));
                if ($d instanceof CarbonInterface) {
                    $candidates[] = [$m[0][1], $d->toDateString(), trim($m[0][0])];
                }
            }
        };

        $try('/\b(\d{4})-(\d{2})-(\d{2})\b/', fn ($m) => $this->make((int) $m[1], (int) $m[2], (int) $m[3]));
        $try('/\b(\d{1,2})[\/.\-](\d{1,2})(?:[\/.\-](\d{2,4}))?\b/', function ($m) use ($today) {
            $y = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null;
            if ($y !== null && $y < 100) {
                $y += 2000;
            }

            return $this->dayMonth((int) $m[1], (int) $m[2], $y, $today);
        });
        $try('/\b(\d{1,2})(?:er|st|nd|rd|th)?\s+('.$months.')\.?(?:\s+(\d{4}))?\b/', fn ($m) => $this->dayMonth((int) $m[1], self::MONTHS[$m[2]], isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null, $today));
        $try("/\\b(?:apres[- ]demain|day after tomorrow)\\b/", fn () => $today->addDays(2));
        $try("/\\b(?<!apres[- ])(?:demain|tomorrow)\\b/", fn () => $today->addDay());
        $try("/\\b(?:aujourd'hui|aujourdhui|ce jour|today|ce soir|tonight)\\b/", fn () => $today);
        $try('/\b(?:dans|d\'ici|sous|in|within)\s+(\d{1,3}|'.$nums.')\s+(jours?|j|semaines?|sem|mois|days?|weeks?|months?)\b/', function ($m) use ($today) {
            $n = ctype_digit($m[1]) ? (int) $m[1] : (self::NUMBERS[$m[1]] ?? 0);
            if ($n <= 0) {
                return null;
            }

            return match (true) {
                str_starts_with($m[2], 'sem') || str_starts_with($m[2], 'week') => $today->addWeeks($n),
                $m[2] === 'mois' || str_starts_with($m[2], 'month') => $today->addMonthsNoOverflow($n),
                default => $today->addDays($n),
            };
        });
        $try('/\bfin (?:du|de|de ce) mois prochain\b|\bend of next month\b/', fn () => $today->addMonthNoOverflow()->endOfMonth());
        $try('/\bfin (?:du|de|de ce) mois\b|\bend of (?:the |this )?month\b/', fn () => $today->endOfMonth());
        $try('/\bfin (?:de la |de |de cette )?semaine(?: prochaine)?\b|\bend of (?:the |this )?week\b/', function ($m) use ($today) {
            $friday = $today->startOfWeek(CarbonInterface::MONDAY)->addDays(4);
            if (str_contains($m[0], 'prochaine') || $friday->lt($today)) {
                $friday = $friday->addWeek();
            }

            return $friday;
        });
        $try('/\b(?:la )?semaine prochaine\b|\bnext week\b/', fn () => $today->startOfWeek(CarbonInterface::MONDAY)->addWeek());
        $try('/\b(?:le )?mois prochain\b|\bnext month\b/', fn () => $today->addMonthNoOverflow()->startOfMonth());
        $try('/\b('.$days.')(?:\s+(prochain|next|en huit))?\b/', function ($m) use ($today) {
            $target = self::WEEKDAYS[$m[1]];
            $diff = ($target - $today->dayOfWeekIso + 7) % 7;
            if ($diff === 0) {
                $diff = 7;
            }
            if (($m[2] ?? '') === 'en huit') {
                $diff += 7;
            }

            return $today->addDays($diff);
        });

        if ($candidates === []) {
            return null;
        }
        usort($candidates, fn ($a, $b) => $a[0] <=> $b[0]);

        return ['date' => $candidates[0][1], 'match' => $candidates[0][2]];
    }

    private function make(int $y, int $m, int $d): ?CarbonImmutable
    {
        return checkdate($m, $d, $y) ? CarbonImmutable::create($y, $m, $d)->startOfDay() : null;
    }

    private function dayMonth(int $day, int $month, ?int $year, CarbonImmutable $today): ?CarbonImmutable
    {
        if ($year !== null) {
            return $this->make($year, $month, $day);
        }
        $d = $this->make($today->year, $month, $day);
        if ($d && $d->lt($today)) {
            $d = $this->make($today->year + 1, $month, $day);
        }

        return $d;
    }
}
