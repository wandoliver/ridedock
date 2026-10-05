<?php

namespace App\Infrastructure\Http;

use Closure;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

final class PublicActivityFileDownloader
{
    /** @param (Closure(string): list<string>)|null $resolveAddresses */
    public function __construct(private readonly ?Closure $resolveAddresses = null) {}

    public function download(string $url): string
    {
        if (! extension_loaded('curl')) {
            throw new RuntimeException('Safe activity file downloads require the cURL extension.');
        }
        $parts = parse_url($url);
        if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || ($parts['port'] ?? 443) !== 443 || preg_match('/[\x00-\x20\\\\]/', $url)) {
            throw new RuntimeException('Invalid activity file download URL.');
        }
        $host = trim($parts['host'], '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses = [$host];
        } else {
            if (! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                throw new RuntimeException('Invalid activity file download host.');
            }
            $addresses = $this->resolveAddresses !== null ? ($this->resolveAddresses)($host) : $this->addresses($host);
        }
        if ($addresses === []) {
            throw new RuntimeException('Activity file download host could not be resolved.');
        }
        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_PRIV_RANGE)
                || str_starts_with(strtolower($address), 'ff')
                || (! str_contains($address, ':') && (int) explode('.', $address)[0] >= 224)) {
                throw new RuntimeException('Activity file downloads require public IP addresses.');
            }
        }
        $maximumBytes = (int) config('activity-providers.fit.max_file_bytes');
        if ($maximumBytes < 1) {
            throw new RuntimeException('Activity file size limit must be positive.');
        }
        $address = str_contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];
        try {
            $response = Http::connectTimeout(5)->timeout(60)->withoutRedirecting()
                ->accept('application/vnd.ant.fit')->withOptions([
                    // Pin the validated address while retaining normal TLS hostname verification.
                    'curl' => filter_var($host, FILTER_VALIDATE_IP) ? [] : [CURLOPT_RESOLVE => ["{$host}:443:{$address}"]],
                    'proxy' => '',
                    'verify' => true,
                    'decode_content' => false,
                    'on_headers' => static function (ResponseInterface $response) use ($maximumBytes): void {
                        if (! in_array(strtolower($response->getHeaderLine('Content-Encoding')), ['', 'identity'], true)) {
                            throw new RuntimeException('Activity files must use an uncompressed response.');
                        }
                        $length = $response->getHeaderLine('Content-Length');
                        if ($length !== '' && (! ctype_digit($length) || (float) $length > $maximumBytes)) {
                            throw new RuntimeException('Activity file exceeds the size limit.');
                        }
                    },
                    'progress' => static function (float $total, float $downloaded) use ($maximumBytes): void {
                        if ($downloaded > $maximumBytes) {
                            throw new RuntimeException('Activity file exceeds the size limit.');
                        }
                    },
                ])->get($url);
            $contents = $response->body();
            if (! $response->successful() || $contents === '' || strlen($contents) > $maximumBytes
                || ! in_array(strtolower($response->header('Content-Encoding')), ['', 'identity'], true)) {
                throw new RuntimeException('Activity file download failed or exceeds the size limit.');
            }

            return $contents;
        } catch (Throwable) {
            // Signed download URLs and response bodies must not reach logs or failed-job records.
            throw new RuntimeException('Activity file download failed or exceeds the size limit.');
        }
    }

    /** @return list<string> */
    private function addresses(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];
        foreach ($records ?: [] as $record) {
            if (isset($record['ip'])) {
                $addresses[] = $record['ip'];
            } elseif (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
