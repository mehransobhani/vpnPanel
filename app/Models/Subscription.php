<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    public const ACTIVE = 'active';

    public const EXPIRED = 'expired';

    public const EXHAUSTED = 'exhausted';

    public const DISABLED = 'disabled';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_online_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class)
            ->withPivot(['state', 'message', 'synced_at'])
            ->withTimestamps();
    }

    public function trafficLogs(): HasMany
    {
        return $this->hasMany(TrafficLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::ACTIVE);
    }

    protected function usedTraffic(): Attribute
    {
        return Attribute::get(fn () => $this->upload + $this->download);
    }

    protected function remainingTraffic(): Attribute
    {
        return Attribute::get(function () {
            if ($this->traffic_limit === 0) {
                return null; // نامحدود
            }

            return max(0, $this->traffic_limit - $this->used_traffic);
        });
    }

    protected function trafficPercent(): Attribute
    {
        return Attribute::get(function () {
            if ($this->traffic_limit === 0) {
                return 0;
            }

            return min(100, round($this->used_traffic / $this->traffic_limit * 100, 1));
        });
    }

    protected function daysLeft(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->expires_at) {
                return null;
            }

            return max(0, (int) now()->startOfDay()->diffInDays($this->expires_at->startOfDay(), false));
        });
    }

    public function isUsable(): bool
    {
        return $this->status === self::ACTIVE
            && (! $this->expires_at || $this->expires_at->isFuture())
            && ($this->traffic_limit === 0 || $this->used_traffic < $this->traffic_limit);
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
