<?php

namespace App\Http\Controllers;

use App\Services\RouteAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouteAnalysisController extends Controller
{
    public function __construct(
        protected RouteAnalysisService $analysisService
    ) {}

    public function calculateAndAnalyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origin_lat' => ['required', 'numeric', 'between:-90,90'],
            'origin_lng' => ['required', 'numeric', 'between:-180,180'],
            'dest_lat' => ['required', 'numeric', 'between:-90,90'],
            'dest_lng' => ['required', 'numeric', 'between:-180,180'],
            'buffer_meters' => ['nullable', 'numeric', 'min:5', 'max:500'],
        ]);

        $bufferMeters = (float) ($validated['buffer_meters'] ?? 40.0);
        $streetRoute = $this->analysisService->fetchStreetRoute(
            (float) $validated['origin_lat'],
            (float) $validated['origin_lng'],
            (float) $validated['dest_lat'],
            (float) $validated['dest_lng']
        );

        $coordinates = $streetRoute['coordinates'] ?? [
            [(float) $validated['origin_lng'], (float) $validated['origin_lat']],
            [(float) $validated['dest_lng'], (float) $validated['dest_lat']],
        ];

        $analysis = $this->analysisService->analyzeRoute($coordinates, $bufferMeters);

        return response()->json(array_merge($analysis, [
            'coordinates' => $coordinates,
            'distance_meters' => $streetRoute['distance_meters'] ?? 0,
            'duration_seconds' => $streetRoute['duration_seconds'] ?? 0,
            'is_street_routed' => isset($streetRoute['coordinates']),
        ]));
    }

    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coordinates' => ['required', 'array', 'min:2'],
            'coordinates.*' => ['required', 'array', 'size:2'],
            'coordinates.*.0' => ['required', 'numeric'], // longitude
            'coordinates.*.1' => ['required', 'numeric'], // latitude
            'buffer_meters' => ['nullable', 'numeric', 'min:5', 'max:500'],
        ]);

        $bufferMeters = (float) ($validated['buffer_meters'] ?? 40.0);
        $coordinates = $validated['coordinates'];

        $result = $this->analysisService->analyzeRoute($coordinates, $bufferMeters);

        return response()->json($result);
    }
}
