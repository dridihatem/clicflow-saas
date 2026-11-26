<?php

namespace App\Enums;

enum TaskCommentVisibility: string
{
    case Internal = 'internal';
    case Shared = 'shared';

    public function label(): string
    {
        return match ($this) {
            self::Internal => __('task_comment_visibility_internal'),
            self::Shared => __('task_comment_visibility_shared'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
