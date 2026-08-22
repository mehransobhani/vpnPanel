<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const CANCELED = 'canceled';

    public const REFUNDED = 'refunded';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->code ??= 'ORD-'.strtoupper(Str::random(10));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
