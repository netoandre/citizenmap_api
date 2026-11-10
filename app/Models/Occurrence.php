<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Occurrence extends Model
{
    use HasFactory;

    public const TYPE_WASTE_DUMPING = 'waste_dumping';
    public const TYPE_UNPAVED_STREET = 'unpaved_street';
    public const TYPE_PAVEMENT_REPAIR = 'pavement_repair';
    public const TYPE_FLOOD_PRONE = 'flood_prone';
    public const TYPE_STREET_LIGHTING = 'street_lighting';

    public const TYPES = [
        self::TYPE_WASTE_DUMPING,
        self::TYPE_UNPAVED_STREET,
        self::TYPE_PAVEMENT_REPAIR,
        self::TYPE_FLOOD_PRONE,
        self::TYPE_STREET_LIGHTING,
    ];

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'description',
        'neighborhood',
        'address',
        'latitude',
        'longitude',
        'photo_url',
        'status',
        'severity',
        'confirmations_count',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'confirmations_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(OccurrenceConfirmation::class);
    }

    public function confirmedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'occurrence_confirmations')->withTimestamps();
    }

    public function scopeFilterType($query, $type)
    {
        if (!empty($type) && $type !== 'all') {
            if (is_array($type)) {
                return $query->whereIn('type', $type);
            }
            return $query->where('type', $type);
        }
        return $query;
    }

    public function scopeFilterNeighborhood($query, $neighborhood)
    {
        if (!empty($neighborhood) && $neighborhood !== 'all') {
            return $query->where('neighborhood', $neighborhood);
        }
        return $query;
    }

    public function scopeFilterStatus($query, $status)
    {
        if (!empty($status) && $status !== 'all') {
            return $query->where('status', $status);
        }
        return $query;
    }

    /**
     * Scope a query to only include occurrences within a given distance (meters) using PostGIS if available,
     * or spherical haversine formula for SQLite compatibility.
     */
    public function scopeWithinDistance($query, float $latitude, float $longitude, float $distanceMeters = 500)
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            return $query->whereRaw(
                "ST_DWithin(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)",
                [$longitude, $latitude, $distanceMeters]
            );
        }

        // Fallback for SQLite / generic SQL: Haversine distance in meters
        $earthRadius = 6371000;
        $haversine = "(? * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";

        return $query->whereRaw("$haversine <= ?", [$earthRadius, $latitude, $longitude, $latitude, $distanceMeters]);
    }

    /**
     * Convert to standard GeoJSON Feature format.
     */
    public function toGeoJsonFeature(?int $currentUserId = null): array
    {
        $hasConfirmed = false;
        if ($currentUserId) {
            $hasConfirmed = $this->confirmations()->where('user_id', $currentUserId)->exists();
        }

        return [
            'type' => 'Feature',
            'id' => $this->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $this->longitude, (float) $this->latitude],
            ],
            'properties' => [
                'id' => $this->id,
                'user_id' => $this->user_id,
                'author_name' => $this->user?->name ?? 'Anônimo',
                'type' => $this->type,
                'title' => $this->title,
                'description' => $this->description,
                'neighborhood' => $this->neighborhood,
                'address' => $this->address,
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
                'photo_url' => $this->photo_url,
                'status' => $this->status,
                'severity' => $this->severity,
                'confirmations_count' => (int) $this->confirmations_count,
                'has_confirmed' => $hasConfirmed,
                'created_at' => $this->created_at?->toIso8601String(),
                'created_at_human' => $this->created_at?->diffForHumans(),
            ],
        ];
    }
}
