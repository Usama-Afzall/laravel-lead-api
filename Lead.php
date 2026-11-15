<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    /**
     * Only the fields the controller sets are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'message',
        'source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'ip_address',
        'user_agent',
        'handled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    /**
     * Leads nobody has processed yet (handled_at is still null).
     */
    public function scopeUnhandled(Builder $query): Builder
    {
        return $query->whereNull('handled_at');
    }

    /**
     * Leads from the same email with the same message inside the last
     * N minutes. Used to swallow accidental double submits.
     */
    public function scopeRecentDuplicate(
        Builder $query,
        string $email,
        string $message,
        int $minutes = 10,
    ): Builder {
        return $query
            ->where('email', $email)
            ->where('message', $message)
            ->where('created_at', '>=', now()->subMinutes($minutes));
    }
}
