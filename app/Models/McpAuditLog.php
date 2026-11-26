<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class McpAuditLog extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @param  array<string, mixed>  $attrs
     */
    public static function record(array $attrs): void
    {
        try {
            self::query()->create(array_merge([
                'created_at' => now(),
            ], $attrs));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
