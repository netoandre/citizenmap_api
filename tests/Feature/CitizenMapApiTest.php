<?php

namespace Tests\Feature;

use App\Models\Occurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CitizenMapApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed database
        $this->seed();
    }

    public function test_public_user_can_list_occurrences_as_geojson(): void
    {
        $response = $this->getJson('/api/occurrences');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'id',
                        'geometry' => ['type', 'coordinates'],
                        'properties' => [
                            'id',
                            'type',
                            'title',
                            'neighborhood',
                            'latitude',
                            'longitude',
                            'confirmations_count',
                        ],
                    ],
                ],
                'total',
            ]);
    }

    public function test_public_user_can_filter_occurrences_by_type_and_neighborhood(): void
    {
        $response = $this->getJson('/api/occurrences?type=flood_prone&neighborhood=Reduto');

        $response->assertStatus(200);
        $features = $response->json('features');

        foreach ($features as $f) {
            $this->assertEquals('flood_prone', $f['properties']['type']);
            $this->assertEquals('Reduto', $f['properties']['neighborhood']);
        }
    }

    public function test_public_user_can_see_confirmations_count(): void
    {
        $occurrence = Occurrence::first();

        $response = $this->getJson("/api/occurrences/{$occurrence->id}");

        $response->assertStatus(200)
            ->assertJsonPath('occurrence.properties.confirmations_count', $occurrence->confirmations_count);
    }

    public function test_unauthenticated_user_cannot_create_occurrence(): void
    {
        $payload = [
            'type' => 'pavement_repair',
            'title' => 'Buraco na rua',
            'neighborhood' => 'Centro',
            'latitude' => -19.92,
            'longitude' => -43.93,
        ];

        $response = $this->postJson('/api/occurrences', $payload);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_create_occurrence(): void
    {
        $user = User::first();

        $payload = [
            'type' => 'waste_dumping',
            'title' => 'Lixo acumulado na praça',
            'description' => 'Muitas sacolas e caixas.',
            'neighborhood' => 'Savassi',
            'latitude' => -19.935,
            'longitude' => -43.932,
            'severity' => 'medium',
        ];

        $response = $this->actingAs($user)->postJson('/api/occurrences', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('occurrence.properties.title', 'Lixo acumulado na praça')
            ->assertJsonPath('occurrence.properties.confirmations_count', 1);

        $this->assertDatabaseHas('occurrences', [
            'title' => 'Lixo acumulado na praça',
            'neighborhood' => 'Savassi',
        ]);
    }

    public function test_authenticated_user_can_toggle_confirmation(): void
    {
        $user = User::factory()->create();
        $occurrence = Occurrence::first();
        $initialCount = $occurrence->confirmations_count;

        // 1. Confirm (Like)
        $res1 = $this->actingAs($user)->postJson("/api/occurrences/{$occurrence->id}/confirm");
        $res1->assertStatus(200)
            ->assertJsonPath('has_confirmed', true)
            ->assertJsonPath('confirmations_count', $initialCount + 1);

        // 2. Unconfirm (Unlike toggle)
        $res2 = $this->actingAs($user)->postJson("/api/occurrences/{$occurrence->id}/confirm");
        $res2->assertStatus(200)
            ->assertJsonPath('has_confirmed', false)
            ->assertJsonPath('confirmations_count', $initialCount);
    }

    public function test_route_analysis_detects_hazards(): void
    {
        $route = [
            [-48.498500, -1.449200], // Start at Reduto (near flood occurrence)
            [-48.486200, -1.444500], // Via Umarizal (near pothole)
        ];

        $response = $this->postJson('/api/routes/analyze', [
            'coordinates' => $route,
            'buffer_meters' => 50,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('has_conflicts', true)
            ->assertJsonStructure([
                'has_conflicts',
                'total_conflicts',
                'critical_conflicts',
                'conflicts' => [
                    '*' => [
                        'id',
                        'type',
                        'title',
                        'distance_meters',
                        'impact_alert',
                    ]
                ],
                'recommendation' => [
                    'level',
                    'title_pt',
                    'title_en',
                ]
            ]);
    }
}
