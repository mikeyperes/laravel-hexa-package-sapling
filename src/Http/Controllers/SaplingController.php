<?php

namespace hexa_package_sapling\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use hexa_package_sapling\Services\SaplingService;
use hexa_core\Models\Setting;

/**
 * SaplingController — handles raw view and API endpoints for the Sapling package.
 */
class SaplingController extends Controller
{
    /**
     * Show the raw development/test page.
     *
     * @return \Illuminate\View\View
     */
    public function raw()
    {
        $apiKey = Setting::getValue('sapling_api_key', '');
        $maskedKey = $apiKey ? str_repeat('*', max(0, strlen($apiKey) - 4)) . substr($apiKey, -4) : '';

        return view('sapling::raw.index', [
            'hasApiKey' => !empty($apiKey),
            'maskedKey' => $maskedKey,
        ]);
    }

    /**
     * Detect AI-generated content via Sapling API.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function detect(Request $request)
    {
        $request->validate(['text' => 'required|string|min:50']);

        $service = app(SaplingService::class);
        $result = $service->detect($request->input('text'));

        return response()->json($result);
    }
}
