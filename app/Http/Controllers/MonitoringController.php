<?php

namespace App\Http\Controllers;

use App\Services\ConnectionMonitorService;
use App\Services\KhanzaConfigService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MonitoringController extends Controller
{
    private $monitorService;
    private $configService;

    public function __construct(ConnectionMonitorService $monitorService, KhanzaConfigService $configService)
    {
        $this->monitorService = $monitorService;
        $this->configService = $configService;
    }

    public function index()
    {
        // For the initial page load, we just pass the endpoint configs
        // The React frontend will ping via Ajax to show the loading state nicely.
        $endpoints = $this->configService->getAllEndpoints();
        
        return Inertia::render('Dashboard', [
            'endpoints' => $endpoints
        ]);
    }

    public function ping(Request $request)
    {
        $id = $request->get('id');
        
        if ($id) {
            $endpoints = $this->configService->getAllEndpoints();
            if (isset($endpoints[$id])) {
                $result = $this->monitorService->pingEndpoint($endpoints[$id]);
                return response()->json([$id => $result]);
            }
            return response()->json(['error' => 'Endpoint not found'], 404);
        }

        $results = $this->monitorService->pingAll();
        return response()->json($results);
    }
}
