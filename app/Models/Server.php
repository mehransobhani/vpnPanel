<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * نود Xray — همان سروری که خودِ پنل روی آن اجرا می‌شود.
 *
 * پنل تک‌نودی است: دقیقاً یک ردیف اینجا وجود دارد و با دستور
 * `panel:setup-local-node` ساخته و به‌روزرسانی می‌شود.
 */
class Server extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function inbounds(): HasMany
    {
        return $this->hasMany(Inbound::class);
    }

    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Subscription::class)
            ->withPivot(['state', 'message', 'synced_at'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** تنها نود پنل؛ اگر هنوز راه‌اندازی نشده باشد null است. */
    public static function node(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at?->gt(now()->subHour()) ?? false;
    }
}
