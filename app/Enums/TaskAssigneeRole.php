<?php

namespace App\Enums;

enum TaskAssigneeRole: string
{
    case Follower = 'follower';
    case Editor = 'editor';

    public function label(): string
    {
        return match ($this) {
            self::Follower => __('task_assignee_role_follower'),
            self::Editor => __('task_assignee_role_editor'),
        };
    }

    public function canChangeStatus(): bool
    {
        return $this === self::Editor;
    }

    public function canComment(): bool
    {
        return true;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
