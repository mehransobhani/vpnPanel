<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'ssh_password' => 'encrypted',
            'ssh_private_key' => 'encrypted',
        ];
    }

    public function inbounds(): HasMany
    {
        return $this->hasMany(Inbound::class);
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class);
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

    /** ظرفیت بر اساس سرویس‌های فعال سنجیده می‌شود؛ منقضی‌ها روی نود نیستند. */
    protected function isFull(): Attribute
    {
        return Attribute::get(fn () => $this->capacity > 0
            && $this->subscriptions()->active()->count() >= $this->capacity);
    }
}
