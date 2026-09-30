<?php

namespace App\Http\Middleware;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

class SecureHttpMiddleware
{
    /** @var array<string> */
    private static array $privateSubnets = [
        '127.0.0.0/8',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '169.254.0.0/16',
        '0.0.0.0/8',
        '::1/128',
        'fe80::/10',
        'fc00::/7',
        'ff00::/8',
    ];

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $host = $request->getUri()->getHost();
            if ($host === '') {
                throw new RuntimeException('Request has no host.');
            }

            $ips = $this->resolveDns($host);
            if ($ips === []) {
                throw new RuntimeException("Could not resolve host: {$host}");
            }

            foreach ($ips as $ip) {
                if ($this->isPrivateIp($ip)) {
                    throw new RuntimeException("Access blocked: {$host} resolves to private/internal IP {$ip}.");
                }
            }

            return $handler($request, $options);
        };
    }

    private function isPrivateIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            && in_array(strtolower($ip), ['::1', 'fe80::', 'fe80:0:0:0:0:0:0:1', 'ff00::'], true)) {
            return true;
        }

        foreach (self::$privateSubnets as $subnet) {
            if ($this->ipInNetwork($ip, $subnet)) {
                return true;
            }
        }

        return false;
    }

    private function ipInNetwork(string $ip, string $range): bool
    {
        [$subnet, $bits] = explode('/', $range, 2);
        $ipPacked = @inet_pton($ip);
        $subnetPacked = @inet_pton($subnet);

        if ($ipPacked === false || $subnetPacked === false || strlen($ipPacked) !== strlen($subnetPacked)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($bytes > 0 && substr($ipPacked, 0, $bytes) !== substr($subnetPacked, 0, $bytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = ~(0xFF >> $remainingBits);

        return (ord($ipPacked[$bytes]) & $mask) === (ord($subnetPacked[$bytes]) & $mask);
    }

    /**
     * @return array<string>
     */
    private function resolveDns(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @gethostbynamel($host) ?: [];

        if (function_exists('dns_get_record')) {
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (isset($record['ipv6'])) {
                    $records[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($records));
    }
}
