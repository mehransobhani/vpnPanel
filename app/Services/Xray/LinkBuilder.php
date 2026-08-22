<?php

namespace App\Services\Xray;

use App\Models\Inbound;
use App\Models\Subscription;

/**
 * ساخت لینک اشتراک‌گذاری (share link) برای هر اینباند Xray.
 *
 * خروجی‌ها مطابق استاندارد رایج کلاینت‌ها هستند:
 *  - vless://  و trojan://  به صورت URI با query string
 *  - vmess://  به صورت base64 از یک آبجکت JSON (نسخه ۲)
 */
class LinkBuilder
{
    /**
     * تمام لینک‌های یک سرویس روی سرورهای تخصیص‌داده‌شده.
     *
     * @return array<int, string>
     */
    public function forSubscription(Subscription $subscription): array
    {
        $links = [];

        $servers = $subscription->relationLoaded('servers')
            ? $subscription->servers
            : $subscription->servers()->with('inbounds')->get();

        foreach ($servers->sortBy('sort') as $server) {
            if (! $server->is_active) {
                continue;
            }

            $inbounds = $server->relationLoaded('inbounds')
                ? $server->inbounds
                : $server->inbounds()->get();

            foreach ($inbounds->where('is_active', true)->sortBy('sort') as $inbound) {
                $links[] = $this->build($inbound, $subscription);
            }
        }

        return array_values(array_filter($links));
    }

    public function build(Inbound $inbound, Subscription $subscription): string
    {
        return match ($inbound->protocol) {
            'vless' => $this->vless($inbound, $subscription),
            'vmess' => $this->vmess($inbound, $subscription),
            'trojan' => $this->trojan($inbound, $subscription),
            default => '',
        };
    }

    public function remark(Inbound $inbound, Subscription $subscription): string
    {
        $server = $inbound->server;

        $remark = strtr($inbound->remark_template ?: '{brand}-{server}', [
            '{brand}' => config('panel.brand'),
            '{server}' => $server->name,
            '{country}' => strtoupper((string) $server->country),
            '{tag}' => $inbound->tag,
            '{protocol}' => strtoupper($inbound->protocol),
            '{plan}' => $subscription->plan?->name ?? '',
            '{remark}' => $subscription->remark,
            '{user}' => $subscription->user?->name ?? '',
        ]);

        return trim(preg_replace('/\s+/', ' ', $remark));
    }

    private function vless(Inbound $inbound, Subscription $subscription): string
    {
        $query = array_merge(
            ['encryption' => 'none'],
            $this->transportParams($inbound),
            $this->securityParams($inbound),
        );

        // flow فقط روی TCP + (tls|reality) معتبر است
        if ($inbound->flow && $inbound->network === 'tcp' && $inbound->security !== 'none') {
            $query['flow'] = $inbound->flow;
        }

        return sprintf(
            'vless://%s@%s?%s#%s',
            $subscription->uuid,
            $this->hostPort($inbound),
            $this->query($query),
            rawurlencode($this->remark($inbound, $subscription)),
        );
    }

    private function trojan(Inbound $inbound, Subscription $subscription): string
    {
        $query = array_merge(
            $this->transportParams($inbound),
            $this->securityParams($inbound),
        );

        return sprintf(
            'trojan://%s@%s?%s#%s',
            rawurlencode($subscription->password),
            $this->hostPort($inbound),
            $this->query($query),
            rawurlencode($this->remark($inbound, $subscription)),
        );
    }

    private function vmess(Inbound $inbound, Subscription $subscription): string
    {
        $payload = [
            'v' => '2',
            'ps' => $this->remark($inbound, $subscription),
            'add' => $inbound->server->address,
            'port' => (string) $inbound->port,
            'id' => $subscription->uuid,
            'aid' => '0',
            'scy' => 'auto',
            'net' => $inbound->network,
            'type' => $inbound->header_type ?: 'none',
            'host' => (string) ($inbound->host ?? ''),
            'path' => (string) ($inbound->path ?? $inbound->service_name ?? ''),
            'tls' => $inbound->security === 'none' ? '' : 'tls',
            'sni' => (string) ($inbound->sni ?? ''),
            'alpn' => (string) ($inbound->alpn ?? ''),
            'fp' => (string) ($inbound->fingerprint ?? ''),
        ];

        return 'vmess://'.base64_encode(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /** پارامترهای لایهٔ انتقال (transport) */
    private function transportParams(Inbound $inbound): array
    {
        $params = ['type' => $inbound->network];

        switch ($inbound->network) {
            case 'ws':
            case 'httpupgrade':
                $params['path'] = $inbound->path ?: '/';
                if ($inbound->host) {
                    $params['host'] = $inbound->host;
                }
                break;

            case 'xhttp':
                $params['path'] = $inbound->path ?: '/';
                $params['mode'] = 'auto';
                if ($inbound->host) {
                    $params['host'] = $inbound->host;
                }
                break;

            case 'grpc':
                $params['serviceName'] = $inbound->service_name ?: '';
                break;

            case 'http':
                $params['path'] = $inbound->path ?: '/';
                if ($inbound->host) {
                    $params['host'] = $inbound->host;
                }
                break;

            case 'tcp':
            default:
                if ($inbound->header_type && $inbound->header_type !== 'none') {
                    $params['headerType'] = $inbound->header_type;
                    if ($inbound->host) {
                        $params['host'] = $inbound->host;
                    }
                    if ($inbound->path) {
                        $params['path'] = $inbound->path;
                    }
                }
                break;
        }

        return $params;
    }

    /** پارامترهای امنیت: tls یا reality */
    private function securityParams(Inbound $inbound): array
    {
        if ($inbound->security === 'none') {
            return ['security' => 'none'];
        }

        $params = ['security' => $inbound->security];

        if ($inbound->sni) {
            $params['sni'] = $inbound->sni;
        }

        if ($inbound->fingerprint) {
            $params['fp'] = $inbound->fingerprint;
        }

        if ($inbound->alpn) {
            $params['alpn'] = $inbound->alpn;
        }

        if ($inbound->security === 'reality') {
            $params['pbk'] = (string) $inbound->reality_public_key;

            if ($inbound->reality_short_id) {
                $params['sid'] = $inbound->reality_short_id;
            }

            if ($inbound->reality_spider_x) {
                $params['spx'] = $inbound->reality_spider_x;
            }

            return $params; // allowInsecure در reality معنا ندارد
        }

        if ($inbound->allow_insecure) {
            $params['allowInsecure'] = '1';
        }

        return $params;
    }

    private function hostPort(Inbound $inbound): string
    {
        $address = $inbound->server->address;

        // IPv6 باید داخل کروشه بیاید
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $address = "[$address]";
        }

        return $address.':'.$inbound->port;
    }

    private function query(array $params): string
    {
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');

        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
