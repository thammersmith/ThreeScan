<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TracerouteService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TracerouteController extends Controller
{
    /**
     * The traceroute service instance.
     *
     * @var TracerouteService
     */
    protected $tracerouteService;

    /**
     * Create a new controller instance.
     *
     * @param TracerouteService $tracerouteService
     * @return void
     */
    public function __construct(TracerouteService $tracerouteService)
    {
        $this->tracerouteService = $tracerouteService;
    }

    /**
     * Execute a traceroute to the specified host.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trace(Request $request): JsonResponse
    {
        // Validate the request
        $validated = $request->validate([
            'host' => 'required|string',
            'name' => 'nullable|string',
            'max_hops' => 'nullable|integer|min:1|max:64',
            'timeout' => 'nullable|integer|min:1|max:30',
            'queries' => 'nullable|integer|min:1|max:10',
        ]);

        try {
            // Extract options
            $options = [];
            if (isset($validated['max_hops'])) {
                $options['max_hops'] = $validated['max_hops'];
            }
            if (isset($validated['timeout'])) {
                $options['timeout'] = $validated['timeout'];
            }
            if (isset($validated['queries'])) {
                $options['queries'] = $validated['queries'];
            }

            // Execute the traceroute
            $result = $this->tracerouteService->trace(
                $validated['host'],
                $validated['name'] ?? '',
                $options
            );

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
