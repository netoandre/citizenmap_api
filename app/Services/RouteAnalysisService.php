<?php

namespace App\Services;

use App\Models\Occurrence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class RouteAnalysisService
{
    /**
     * Query OSRM routing service to get actual road/street path coordinates.
     */
    public function fetchStreetRoute(float $originLat, float $originLng, float $destLat, float $destLng): ?array
    {
        try {
            $url = "https://router.project-osrm.org/route/v1/driving/{$originLng},{$originLat};{$destLng},{$destLat}?overview=full&geometries=geojson";
            $response = Http::timeout(10)->withHeaders([
                'User-Agent' => 'CitizenMap/1.0',
            ])->get($url);

            if ($response->successful() && isset($response['routes'][0])) {
                $route = $response['routes'][0];
                return [
                    'coordinates' => $route['geometry']['coordinates'], // [[lng, lat], ...]
                    'distance_meters' => (float) ($route['distance'] ?? 0),
                    'duration_seconds' => (float) ($route['duration'] ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            // Fallback handled by caller
        }

        return null;
    }

    /**
     * Analyze route conflicts with active occurrences within a buffer distance (in meters).
     *
     * @param array $coordinates Array of [longitude, latitude] points representing the route path
     * @param float $bufferMeters Search radius in meters around the route line (default 40m)
     * @return array
     */
    public function analyzeRoute(array $coordinates, float $bufferMeters = 40.0): array
    {
        if (count($coordinates) < 2) {
            return [
                'has_conflicts' => false,
                'total_conflicts' => 0,
                'buffer_meters' => $bufferMeters,
                'hazard_summary' => [],
                'conflicts' => [],
                'recommendations' => ['status' => 'clean', 'message' => 'Nenhum ponto de conflito identificado na rota.']
            ];
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            return $this->analyzeWithPostGis($coordinates, $bufferMeters);
        }

        return $this->analyzeWithPhpGeometry($coordinates, $bufferMeters);
    }

    /**
     * Native PostGIS query using ST_DWithin and ST_Distance on geography.
     */
    protected function analyzeWithPostGis(array $coordinates, float $bufferMeters): array
    {
        $lineGeoJson = json_encode([
            'type' => 'LineString',
            'coordinates' => $coordinates,
        ]);

        $query = "
            SELECT id, type, title, description, neighborhood, address, latitude, longitude,
                   severity, status, photo_url, confirmations_count,
                   ROUND(ST_Distance(geom::geography, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography)::numeric, 1) AS distance_meters
            FROM occurrences
            WHERE status != 'resolved'
              AND ST_DWithin(geom::geography, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography, ?)
            ORDER BY distance_meters ASC
        ";

        $results = DB::select($query, [$lineGeoJson, $lineGeoJson, $bufferMeters]);

        return $this->formatAnalysisResults($results, $bufferMeters);
    }

    /**
     * Algorithmic geometric analysis calculating shortest distance from point to line segments.
     * Compatible with SQLite or fallback environments.
     */
    protected function analyzeWithPhpGeometry(array $coordinates, float $bufferMeters): array
    {
        // Fetch non-resolved occurrences
        $occurrences = Occurrence::where('status', '!=', 'resolved')->get();
        $conflicts = [];

        foreach ($occurrences as $occ) {
            $minDistance = INF;
            $ptLon = (float) $occ->longitude;
            $ptLat = (float) $occ->latitude;

            // Check distance to each segment of the route
            for ($i = 0; $i < count($coordinates) - 1; $i++) {
                $p1Lon = (float) $coordinates[$i][0];
                $p1Lat = (float) $coordinates[$i][1];
                $p2Lon = (float) $coordinates[$i + 1][0];
                $p2Lat = (float) $coordinates[$i + 1][1];

                $dist = $this->distanceToSegment($ptLat, $ptLon, $p1Lat, $p1Lon, $p2Lat, $p2Lon);
                if ($dist < $minDistance) {
                    $minDistance = $dist;
                }
            }

            if ($minDistance <= $bufferMeters) {
                $conflicts[] = (object) [
                    'id' => $occ->id,
                    'type' => $occ->type,
                    'title' => $occ->title,
                    'description' => $occ->description,
                    'neighborhood' => $occ->neighborhood,
                    'address' => $occ->address,
                    'latitude' => $occ->latitude,
                    'longitude' => $occ->longitude,
                    'severity' => $occ->severity,
                    'status' => $occ->status,
                    'photo_url' => $occ->photo_url,
                    'confirmations_count' => $occ->confirmations_count,
                    'distance_meters' => round($minDistance, 1),
                ];
            }
        }

        // Sort by closest distance
        usort($conflicts, fn($a, $b) => $a->distance_meters <=> $b->distance_meters);

        return $this->formatAnalysisResults($conflicts, $bufferMeters);
    }

    /**
     * Format and generate risk warnings for the response.
     */
    protected function formatAnalysisResults(array $conflicts, float $bufferMeters): array
    {
        $hasConflicts = count($conflicts) > 0;
        $hazardSummary = [
            'waste_dumping' => 0,
            'unpaved_street' => 0,
            'pavement_repair' => 0,
            'flood_prone' => 0,
            'street_lighting' => 0,
        ];

        $criticalCount = 0;
        $conflictList = [];

        foreach ($conflicts as $c) {
            $type = $c->type;
            if (isset($hazardSummary[$type])) {
                $hazardSummary[$type]++;
            } else {
                $hazardSummary[$type] = 1;
            }

            if ($type === Occurrence::TYPE_FLOOD_PRONE || $c->severity === 'critical' || $c->severity === 'high') {
                $criticalCount++;
            }

            $conflictList[] = [
                'id' => $c->id,
                'type' => $c->type,
                'title' => $c->title,
                'description' => $c->description,
                'neighborhood' => $c->neighborhood,
                'address' => $c->address,
                'latitude' => (float) $c->latitude,
                'longitude' => (float) $c->longitude,
                'severity' => $c->severity,
                'status' => $c->status,
                'photo_url' => $c->photo_url,
                'confirmations_count' => (int) $c->confirmations_count,
                'distance_meters' => (float) $c->distance_meters,
                'impact_alert' => $this->getImpactAlert($c->type, $c->distance_meters),
            ];
        }

        $recommendation = $this->buildRecommendation($hasConflicts, $criticalCount, $hazardSummary);

        return [
            'has_conflicts' => $hasConflicts,
            'total_conflicts' => count($conflicts),
            'critical_conflicts' => $criticalCount,
            'buffer_meters' => $bufferMeters,
            'hazard_summary' => $hazardSummary,
            'conflicts' => $conflictList,
            'recommendation' => $recommendation,
        ];
    }

    protected function getImpactAlert(string $type, float $distanceMeters): string
    {
        $dist = round($distanceMeters);
        return match ($type) {
            Occurrence::TYPE_FLOOD_PRONE => "Risco de alagamento/enxurrada a {$dist}m do traçado.",
            Occurrence::TYPE_PAVEMENT_REPAIR => "Pavimento danificado com buracos a {$dist}m.",
            Occurrence::TYPE_UNPAVED_STREET => "Trecho de terra sem pavimentação a {$dist}m.",
            Occurrence::TYPE_STREET_LIGHTING => "Iluminação pública deficiente nas imediações ({$dist}m).",
            Occurrence::TYPE_WASTE_DUMPING => "Descarte irregular de lixo e entulho a {$dist}m.",
            default => "Ocorrência urbana detectada a {$dist}m da rota.",
        };
    }

    protected function buildRecommendation(bool $hasConflicts, int $criticalCount, array $summary): array
    {
        if (!$hasConflicts) {
            return [
                'level' => 'safe',
                'title_pt' => 'Rota Segura',
                'title_en' => 'Safe Route',
                'description_pt' => 'Nenhuma ocorrência ou obstáculo urbano foi reportado ao longo deste trajeto.',
                'description_en' => 'No occurrences or urban hazards have been reported along this path.',
                'action_label_pt' => 'Prosseguir com confiança',
                'action_label_en' => 'Proceed with confidence',
            ];
        }

        if ($summary['flood_prone'] > 0 || $criticalCount > 0) {
            return [
                'level' => 'danger',
                'title_pt' => 'Atenção Crítica: Risco no Trajeto',
                'title_en' => 'Critical Warning: Hazard along Route',
                'description_pt' => "Esta rota cruza áreas com {$summary['flood_prone']} registro(s) de alagamento ou perigo elevado. Recomendamos buscar uma rota alternativa.",
                'description_en' => "This route crosses zones with {$summary['flood_prone']} flood hazard(s) or high risk. A detour or alternate route is recommended.",
                'action_label_pt' => 'Avaliar desvio ou prosseguir com cautela',
                'action_label_en' => 'Evaluate detour or proceed with caution',
            ];
        }

        return [
            'level' => 'warning',
            'title_pt' => 'Alerta Moderado: Trechos Irregulares',
            'title_en' => 'Moderate Warning: Irregular Sections',
            'description_pt' => 'Foram identificadas ocorrências de pavimentação, lixo ou iluminação no percurso. Trajeto transitável com atenção.',
            'description_en' => 'Pavement, debris or lighting issues were detected on the way. Navigable with attention.',
            'action_label_pt' => 'Prosseguir com atenção',
            'action_label_en' => 'Proceed with attention',
        ];
    }

    /**
     * Approximate shortest distance (meters) from point to line segment on Earth surface.
     */
    protected function distanceToSegment(float $latP, float $lonP, float $latA, float $lonA, float $latB, float $lonB): float
    {
        // Project degrees to local meters using Equirectangular approximation
        $rad = M_PI / 180.0;
        $latMid = (($latA + $latB) / 2.0) * $rad;
        $kx = cos($latMid) * 111320.0;
        $ky = 110540.0;

        $px = $lonP * $kx;
        $py = $latP * $ky;
        $ax = $lonA * $kx;
        $ay = $latA * $ky;
        $bx = $lonB * $kx;
        $by = $latB * $ky;

        $dx = $bx - $ax;
        $dy = $by - $ay;

        $lenSq = $dx * $dx + $dy * $dy;
        if ($lenSq == 0) {
            return hypot($px - $ax, $py - $ay);
        }

        // Projection factor t
        $t = max(0, min(1, (($px - $ax) * $dx + ($py - $ay) * $dy) / $lenSq));

        $projX = $ax + $t * $dx;
        $projY = $ay + $t * $dy;

        return hypot($px - $projX, $py - $projY);
    }
}
