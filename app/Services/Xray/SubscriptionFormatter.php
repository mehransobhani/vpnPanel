<?php

namespace App\Services\Xray;

use App\Models\Subscription;

/**
 * تبدیل لیست لینک‌های یک سرویس به فرمت‌هایی که کلاینت‌های مختلف می‌فهمند.
 */
class SubscriptionFormatter
{
    public function __construct(private readonly LinkBuilder $links) {}

    /** v2rayNG / v2rayN / Nekobox — لیست base64 شده */
    public function base64(Subscription $subscription): string
    {
        return base64_encode($this->raw($subscription));
    }

    /** لیست خام، هر لینک در یک خط */
    public function raw(Subscription $subscription): string
    {
        return implode("\n", $this->links->forSubscription($subscription));
    }

    /** Clash Meta / Mihomo — خروجی YAML */
    public function clash(Subscription $subscription): string
    {
        $proxies = [];

        $servers = $subscription->servers()->active()->with('inbounds')->get()->sortBy('sort');

        foreach ($servers as $server) {
            foreach ($server->inbounds->where('is_active', true)->sortBy('sort') as $inbound) {
                if ($proxy = $this->clashProxy($inbound, $subscription)) {
                    $proxies[] = $proxy;
                }
            }
        }

        $names = array_column($proxies, 'name');

        $yaml = "# ".config('panel.brand')." — {$subscription->remark}\n";
        $yaml .= "mixed-port: 7890\nallow-lan: false\nmode: rule\nlog-level: info\n\n";
        $yaml .= "proxies:\n";

        foreach ($proxies as $proxy) {
            $yaml .= $this->yamlInlineMap($proxy);
        }

        $yaml .= "\nproxy-groups:\n";
        $yaml .= "  - name: PROXY\n    type: select\n    proxies:\n";
        $yaml .= "      - AUTO\n";
        foreach ($names as $name) {
            $yaml .= '      - '.$this->yamlString($name)."\n";
        }
        $yaml .= "  - name: AUTO\n    type: url-test\n    url: http://www.gstatic.com/generate_204\n";
        $yaml .= "    interval: 300\n    tolerance: 50\n    proxies:\n";
        foreach ($names as $name) {
            $yaml .= '      - '.$this->yamlString($name)."\n";
        }

        $yaml .= "\nrules:\n";
        $yaml .= "  - GEOIP,IR,DIRECT\n";
        $yaml .= "  - GEOIP,PRIVATE,DIRECT,no-resolve\n";
        $yaml .= "  - MATCH,PROXY\n";

        return $yaml;
    }

    private function clashProxy($inbound, Subscription $subscription): ?array
    {
        $base = [
            'name' => $this->links->remark($inbound, $subscription),
            'type' => $inbound->protocol,
            'server' => $inbound->server->address,
            'port' => (int) $inbound->port,
            'udp' => true,
        ];

        $proxy = match ($inbound->protocol) {
            'vless' => $base + array_filter([
                'uuid' => $subscription->uuid,
                'flow' => ($inbound->flow && $inbound->network === 'tcp') ? $inbound->flow : null,
            ]),
            'vmess' => $base + ['uuid' => $subscription->uuid, 'alterId' => 0, 'cipher' => 'auto'],
            'trojan' => $base + ['password' => $subscription->password],
            default => null,
        };

        if ($proxy === null) {
            return null;
        }

        if ($inbound->security !== 'none') {
            $proxy['tls'] = true;
            $proxy['servername'] = $inbound->sni ?: $inbound->server->address;

            if ($inbound->fingerprint) {
                $proxy['client-fingerprint'] = $inbound->fingerprint;
            }

            if ($inbound->security === 'reality') {
                $proxy['reality-opts'] = array_filter([
                    'public-key' => $inbound->reality_public_key,
                    'short-id' => $inbound->reality_short_id,
                ]);
            } elseif ($inbound->allow_insecure) {
                $proxy['skip-cert-verify'] = true;
            }
        }

        $proxy['network'] = $inbound->network === 'tcp' ? 'tcp' : $inbound->network;

        if ($inbound->network === 'ws') {
            $proxy['ws-opts'] = array_filter([
                'path' => $inbound->path ?: '/',
                'headers' => $inbound->host ? ['Host' => $inbound->host] : null,
            ]);
        } elseif ($inbound->network === 'grpc') {
            $proxy['grpc-opts'] = ['grpc-service-name' => (string) $inbound->service_name];
        }

        return $proxy;
    }

    /** یک نقشهٔ تک‌سطحی/تودرتو را به آیتم YAML تبدیل می‌کند. */
    private function yamlInlineMap(array $proxy): string
    {
        $out = '';
        $first = true;

        foreach ($proxy as $key => $value) {
            $prefix = $first ? '  - ' : '    ';
            $first = false;

            if (is_array($value)) {
                $out .= "$prefix$key:\n";
                foreach ($value as $k => $v) {
                    if (is_array($v)) {
                        $out .= "        $k:\n";
                        foreach ($v as $k2 => $v2) {
                            $out .= "          $k2: ".$this->yamlScalar($v2)."\n";
                        }
                    } else {
                        $out .= "        $k: ".$this->yamlScalar($v)."\n";
                    }
                }

                continue;
            }

            $out .= "$prefix$key: ".$this->yamlScalar($value)."\n";
        }

        return $out;
    }

    private function yamlScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $this->yamlString((string) $value);
    }

    private function yamlString(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
