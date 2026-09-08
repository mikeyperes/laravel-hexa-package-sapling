<?php

namespace hexa_package_sapling\Http\Controllers;

use hexa_core\Models\Setting;
use hexa_core\Services\CredentialService;
use hexa_package_sapling\Services\SaplingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * SaplingController — handles raw view and API endpoints for the Sapling package.
 */
class SaplingController extends Controller
{
    public function __construct(private readonly CredentialService $credentials) {}

    /**
     * Show the raw development/test page.
     *
     * @return View
     */
    public function raw()
    {
        $apiKey = $this->credentials->get('sapling', 'api_key')
            ?: Setting::getValue('sapling_api_key', '');
        $maskedKey = $apiKey ? str_repeat('*', max(0, strlen($apiKey) - 4)).substr($apiKey, -4) : '';

        return view('sapling::raw.index', [
            'hasApiKey' => ! empty($apiKey),
            'maskedKey' => $maskedKey,
        ]);
    }

    /**
     * Detect AI-generated content via Sapling API.
     *
     * @return JsonResponse
     */
    public function detect(Request $request)
    {
        $request->validate(['text' => 'required|string|min:50']);

        $service = app(SaplingService::class);
        $result = $service->detect($request->input('text'));

        return response()->json($result);
    }
}
