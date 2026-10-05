<?php

namespace Tests\Feature;

use App\Infrastructure\Http\PublicActivityFileDownloader;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ActivityFileDownloadSecurityTest extends TestCase
{
    public static function unsafeTargets(): array
    {
        return [
            ['http://files.example.test/ride.fit', ['1.1.1.1']],
            ['https://files.example.test:8443/ride.fit', ['1.1.1.1']],
            ['https://user:password@files.example.test/ride.fit', ['1.1.1.1']],
            ['https://127.0.0.1/ride.fit', []],
            ['https://169.254.169.254/ride.fit', []],
            ['https://[::1]/ride.fit', []],
            ['https://files.example.test/ride.fit', ['10.0.0.1']],
            ['https://files.example.test/ride.fit', ['1.1.1.1', '192.168.1.1']],
            ['https://files.example.test/ride.fit', ['fd00::1']],
            ['https://files.example.test/ride.fit', []],
        ];
    }

    #[DataProvider('unsafeTargets')]
    public function test_untrusted_targets_are_rejected_before_an_http_request(string $url, array $addresses): void
    {
        $downloader = new PublicActivityFileDownloader(static fn (string $host): array => $addresses);
        try {
            $downloader->download($url);
            $this->fail('Unsafe download must be rejected.');
        } catch (RuntimeException) {
            Http::assertNothingSent();
        }
    }

    public function test_public_downloads_work_with_a_bounded_body(): void
    {
        Http::fake(function ($request, array $options) {
            $this->assertFalse($request->hasHeader('Authorization'));
            $this->assertFalse($options['allow_redirects']);
            $this->assertFalse($options['decode_content']);
            $this->assertTrue($options['verify']);
            $this->assertSame('', $options['proxy']);
            $this->assertSame(['files.example.test:443:1.1.1.1'], $options['curl'][CURLOPT_RESOLVE]);

            return Http::response('synthetic FIT', 200);
        });
        $downloader = new PublicActivityFileDownloader(static fn (string $host): array => ['1.1.1.1']);
        $this->assertSame('synthetic FIT', $downloader->download('https://files.example.test/ride.fit'));
    }

    public function test_redirects_and_oversized_responses_fail_without_exposing_signed_urls(): void
    {
        config()->set('activity-providers.fit.max_file_bytes', 3);
        Http::fakeSequence()->push('', 302, ['Location' => 'http://127.0.0.1/private'])->push('TOO LARGE', 200);
        $downloader = new PublicActivityFileDownloader(static fn (string $host): array => ['1.1.1.1']);
        foreach ([1, 2] as $attempt) {
            try {
                $downloader->download('https://files.example.test/ride.fit?signature=PRIVATE_SIGNATURE');
                $this->fail('Download must fail.');
            } catch (RuntimeException $exception) {
                $this->assertStringNotContainsString('PRIVATE_SIGNATURE', $exception->getMessage());
                $this->assertNull($exception->getPrevious());
            }
        }
        Http::assertSentCount(2);
    }

    public function test_size_limits_are_enforced_by_header_and_transfer_callbacks(): void
    {
        config()->set('activity-providers.fit.max_file_bytes', 3);
        foreach (['headers', 'transfer'] as $phase) {
            Http::swap(new \Illuminate\Http\Client\Factory());
            Http::preventStrayRequests();
            Http::fake(static function ($request, array $options) use ($phase) {
                if ($phase === 'headers') {
                    $options['on_headers'](new \GuzzleHttp\Psr7\Response(200, ['Content-Length' => '4']));
                } else {
                    $options['progress'](0, 4);
                }

                return Http::response('FIT', 200);
            });
            try {
                (new PublicActivityFileDownloader(static fn (string $host): array => ['1.1.1.1']))->download('https://files.example.test/ride.fit');
                $this->fail('Transfer must be stopped at the size limit.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('size limit', $exception->getMessage());
            }
        }
    }

    public function test_compressed_transport_cannot_bypass_the_size_limit(): void
    {
        Http::fake(['files.example.test/*' => Http::response('synthetic compressed bytes', 200, ['Content-Encoding' => 'gzip'])]);
        $this->expectException(RuntimeException::class);
        (new PublicActivityFileDownloader(static fn (string $host): array => ['1.1.1.1']))->download('https://files.example.test/ride.fit');
    }
}
