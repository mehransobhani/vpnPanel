<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inbound extends Model
{
    use HasFactory;

    public const PROTOCOLS = ['vless', 'vmess', 'trojan'];

    public const NETWORKS = ['tcp', 'ws', 'grpc', 'http', 'httpupgrade', 'xhttp'];

    public const SECURITIES = ['none', 'tls', 'reality'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allow_insecure' => 'boolean',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * برچسبی که در Xray برای این کاربر ثبت می‌شود.
     * فرمت email در Xray کلید شمارش مصرف است.
     */
    public function usesReality(): bool
    {
        return $this->security === 'reality';
    }
}
