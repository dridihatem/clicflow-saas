<?php

namespace App\Enums;

enum ProspectActivityType: string
{
    case Source = 'source';
    case Call = 'call';
    case Feedback = 'feedback';
    case Meeting = 'meeting';
    case Quote = 'quote';
    case AiSuggestion = 'ai_suggestion';
    case Mailing = 'mailing';
    case Reminder = 'reminder';
    case Note = 'note';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Source => __('prospect_activity_source'),
            self::Call => __('prospect_activity_call'),
            self::Feedback => __('prospect_activity_feedback'),
            self::Meeting => __('prospect_activity_meeting'),
            self::Quote => __('prospect_activity_quote'),
            self::AiSuggestion => __('prospect_activity_ai'),
            self::Mailing => __('prospect_activity_mailing'),
            self::Reminder => __('prospect_activity_reminder'),
            self::Note => __('prospect_activity_note'),
            self::Converted => __('prospect_activity_converted'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /** Lifecycle stages shown on the prospect pipeline (ordered). */
    /** @return list<self> */
    public static function pipeline(): array
    {
        return [
            self::Source,
            self::Call,
            self::Feedback,
            self::Meeting,
            self::Quote,
            self::AiSuggestion,
            self::Mailing,
            self::Reminder,
            self::Converted,
        ];
    }
}
