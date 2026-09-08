<?php

namespace Tests\Feature;

use hexa_core\Security\Http\OutboundHttpRequest;
use hexa_core\Security\Http\OutboundHttpResponse;
use hexa_core\Security\Http\OutboundUrlGuard;
use hexa_core\Security\Http\SafeOutboundHttpClient;
use hexa_core\Services\CredentialService;
use hexa_package_sapling\Services\SaplingService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

final class SaplingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireInstalledPackage('hexawebsystems/laravel-hexa-package-sapling', SaplingService::class);
        Http::preventStrayRequests();
    }

    public function test_api_key_probe_uses_bounded_pinned_transport(): void
    {
        $requests = [];
        $service = $this->service($requests, new OutboundHttpResponse(200, [], '{"score":0.1}'));

        $result = $service->testApiKey('fixture-key');

        $this->assertTrue($result['success']);
        $this->assertCount(1, $requests);
        $this->assertSame('https://api.sapling.ai/api/v1/aidetect', $requests[0]->target->url);
        $this->assertSame(10, $requests[0]->timeoutSeconds);
        $this->assertSame(4 * 1024 * 1024, $requests[0]->maxResponseBytes);
        $this->assertSame('fixture-key', json_decode((string) $requests[0]->body, true, 512, JSON_THROW_ON_ERROR)['key']);
        Http::assertNothingSent();
    }

    public function test_provider_failures_do_not_leak_credentials_or_details(): void
    {
        $requests = [];
        $service = $this->service($requests, new OutboundHttpResponse(
            500,
            [],
            '{"msg":"fixture-key provider detail"}',
        ));

        $result = $service->testApiKey('fixture-key');
        $serialized = json_encode($result, JSON_THROW_ON_ERROR);

        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('fixture-key', $serialized);
        $this->assertStringNotContainsString('provider detail', $serialized);
        Http::assertNothingSent();
    }

    public function test_detection_preserves_score_and_sentence_result_shape(): void
    {
        $credentials = Mockery::mock(CredentialService::class);
        $credentials->shouldReceive('get')->once()->with('sapling', 'api_key')->andReturn('fixture-key');
        $requests = [];
        $service = $this->service($requests, new OutboundHttpResponse(200, [], json_encode([
            'score' => 0.875,
            'sentence_scores' => [['sentence' => 'Fixture sentence.', 'score' => 0.9]],
        ], JSON_THROW_ON_ERROR)), $credentials);

        $result = $service->detect(str_repeat('Fixture content. ', 5));

        $this->assertTrue($result['success']);
        $this->assertSame(87.5, $result['data']['score']);
        $this->assertSame('Fixture sentence.', $result['data']['sentence_scores'][0]['sentence']);
        $this->assertSame(30, $requests[0]->timeoutSeconds);
        Http::assertNothingSent();
    }

    public function test_service_source_has_no_raw_http_or_exception_detail_fallback(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/src/Services/SaplingService.php');

        $this->assertStringContainsString('SafeOutboundHttpClient', $source);
        $this->assertStringNotContainsString('Facades\\Http', $source);
        $this->assertStringNotContainsString('Http::', $source);
        $this->assertStringNotContainsString('->getMessage()', $source);
        $this->assertStringNotContainsString('->body()', $source);
    }

    /** @param list<OutboundHttpRequest> $requests */
    private function service(
        array &$requests,
        OutboundHttpResponse $response,
        ?CredentialService $credentials = null,
    ): SaplingService {
        $client = new SafeOutboundHttpClient(
            new OutboundUrlGuard(static fn (string $host): array => ['93.184.216.34']),
            static function (OutboundHttpRequest $request) use (&$requests, $response): OutboundHttpResponse {
                $requests[] = $request;

                return $response;
            },
        );

        return new SaplingService($client, $credentials ?? Mockery::mock(CredentialService::class));
    }
}
