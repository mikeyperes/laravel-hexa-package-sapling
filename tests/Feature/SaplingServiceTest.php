<?php

namespace Tests\Feature;

use hexa_package_sapling\Services\SaplingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaplingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireInstalledPackage("hexawebsystems/laravel-hexa-package-sapling", SaplingService::class);
    }

    public function test_api_key_probe_uses_provider_endpoint(): void
    {
        Http::fake(["*api.sapling.ai/*" => Http::response(["score" => 0.1], 200)]);

        $result = app(SaplingService::class)->testApiKey("test-key");

        $this->assertTrue($result["success"]);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), "api.sapling.ai/api/v1/aidetect"));
    }
}
