<?php

namespace App\Http\Controllers;

use App\Models\Occurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OccurrenceController extends Controller
{
    /**
     * Display a listing of occurrences with spatial & thematic filters.
     * Publicly viewable by any user.
     */
    public function index(Request $request): JsonResponse
    {
        $currentUserId = $request->user('sanctum')?->id;

        $query = Occurrence::with('user:id,name');

        // Apply filters
        if ($request->filled('type') && $request->type !== 'all') {
            $types = is_array($request->type) ? $request->type : explode(',', $request->type);
            $query->whereIn('type', $types);
        }

        if ($request->filled('neighborhood') && $request->neighborhood !== 'all') {
            $query->where('neighborhood', $request->neighborhood);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                  ->orWhere('description', 'like', $search)
                  ->orWhere('address', 'like', $search)
                  ->orWhere('neighborhood', 'like', $search);
            });
        }

        // Bounding box filter if supplied by map viewport (minLon, minLat, maxLon, maxLat)
        if ($request->filled(['min_lon', 'min_lat', 'max_lon', 'max_lat'])) {
            $query->whereBetween('longitude', [(float) $request->min_lon, (float) $request->max_lon])
                  ->whereBetween('latitude', [(float) $request->min_lat, (float) $request->max_lat]);
        }

        $occurrences = $query->latest()->get();

        // Check if client requests standard GeoJSON format (ideal for Leaflet)
        $format = $request->query('format', 'geojson');

        if ($format === 'geojson') {
            $features = $occurrences->map(fn($occ) => $occ->toGeoJsonFeature($currentUserId))->values();

            return response()->json([
                'type' => 'FeatureCollection',
                'features' => $features,
                'total' => $features->count(),
                'filters_applied' => [
                    'type' => $request->query('type', 'all'),
                    'neighborhood' => $request->query('neighborhood', 'all'),
                    'status' => $request->query('status', 'all'),
                ]
            ]);
        }

        return response()->json([
            'data' => $occurrences,
            'total' => $occurrences->count(),
        ]);
    }

    /**
     * Store a newly created occurrence. Requires authentication.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(Occurrence::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'neighborhood' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo_url' => ['nullable', 'string', 'url', 'max:2000'],
            'severity' => ['nullable', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
        ]);

        $user = $request->user();

        $occurrence = new Occurrence();
        $occurrence->user_id = $user->id;
        $occurrence->type = $validated['type'];
        $occurrence->title = $validated['title'];
        $occurrence->description = $validated['description'] ?? null;
        $occurrence->neighborhood = $validated['neighborhood'];
        $occurrence->address = $validated['address'] ?? null;
        $occurrence->latitude = $validated['latitude'];
        $occurrence->longitude = $validated['longitude'];
        $occurrence->photo_url = $validated['photo_url'] ?? null;
        $occurrence->severity = $validated['severity'] ?? 'medium';
        $occurrence->status = 'active';
        $occurrence->confirmations_count = 1; // Initial author confirmation
        $occurrence->save();

        // Also register author's confirmation
        $occurrence->confirmations()->create([
            'user_id' => $user->id,
        ]);

        // If PostGIS is the active driver and trigger didn't handle it, update geom
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'UPDATE occurrences SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                [$occurrence->longitude, $occurrence->latitude, $occurrence->id]
            );
        }

        $occurrence->load('user:id,name');

        return response()->json([
            'message' => 'Ocorrência registrada com sucesso / Occurrence registered successfully!',
            'occurrence' => $occurrence->toGeoJsonFeature($user->id),
        ], 201);
    }

    /**
     * Display a specific occurrence. Public.
     */
    public function show(Occurrence $occurrence, Request $request): JsonResponse
    {
        $currentUserId = $request->user('sanctum')?->id;
        $occurrence->load('user:id,name');

        return response()->json([
            'occurrence' => $occurrence->toGeoJsonFeature($currentUserId),
        ]);
    }

    /**
     * Update the occurrence.
     */
    public function update(Request $request, Occurrence $occurrence): JsonResponse
    {
        $user = $request->user();
        if ($occurrence->user_id !== $user->id) {
            return response()->json(['message' => 'Não autorizado / Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'neighborhood' => ['sometimes', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'in_review', 'resolved'])],
            'severity' => ['sometimes', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'photo_url' => ['nullable', 'string', 'url'],
        ]);

        $occurrence->update($validated);
        $occurrence->load('user:id,name');

        return response()->json([
            'message' => 'Ocorrência atualizada com sucesso / Occurrence updated successfully.',
            'occurrence' => $occurrence->toGeoJsonFeature($user->id),
        ]);
    }

    /**
     * Delete an occurrence.
     */
    public function destroy(Occurrence $occurrence, Request $request): JsonResponse
    {
        $user = $request->user();
        if ($occurrence->user_id !== $user->id) {
            return response()->json(['message' => 'Não autorizado / Unauthorized.'], 403);
        }

        $occurrence->delete();

        return response()->json([
            'message' => 'Ocorrência excluída com sucesso / Occurrence deleted successfully.',
        ]);
    }

    /**
     * Get distinct neighborhoods with counts for filtering.
     */
    public function neighborhoods(): JsonResponse
    {
        $neighborhoods = Occurrence::select('neighborhood', DB::raw('count(*) as occurrences_count'))
            ->groupBy('neighborhood')
            ->orderBy('neighborhood')
            ->get();

        return response()->json([
            'neighborhoods' => $neighborhoods,
        ]);
    }

    /**
     * Get aggregate statistics for decision makers & spatial reasoning.
     */
    public function statistics(): JsonResponse
    {
        $total = Occurrence::count();
        $byType = Occurrence::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type');

        $byStatus = Occurrence::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $topNeighborhoods = Occurrence::select('neighborhood', DB::raw('count(*) as count'))
            ->groupBy('neighborhood')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $topCritical = Occurrence::with('user:id,name')
            ->where('status', '!=', 'resolved')
            ->orderByDesc('confirmations_count')
            ->limit(5)
            ->get()
            ->map(fn($o) => $o->toGeoJsonFeature());

        return response()->json([
            'total' => $total,
            'by_type' => $byType,
            'by_status' => $byStatus,
            'top_neighborhoods' => $topNeighborhoods,
            'top_critical' => $topCritical,
        ]);
    }
}
