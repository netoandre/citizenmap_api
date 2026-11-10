<?php

namespace Database\Seeders;

use App\Models\Occurrence;
use App\Models\OccurrenceConfirmation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with demo users and realistic Belém occurrences.
     */
    public function run(): void
    {
        // 1. Create Demo Users
        $maria = User::updateOrCreate(
            ['email' => 'maria.silva@exemplo.com'],
            ['name' => 'Maria Silva', 'password' => Hash::make('password123')]
        );

        $carlos = User::updateOrCreate(
            ['email' => 'carlos.moraes@exemplo.com'],
            ['name' => 'Carlos Moraes', 'password' => Hash::make('password123')]
        );

        $ana = User::updateOrCreate(
            ['email' => 'ana.costa@exemplo.com'],
            ['name' => 'Ana Costa', 'password' => Hash::make('password123')]
        );

        $joao = User::updateOrCreate(
            ['email' => 'joao.pedro@exemplo.com'],
            ['name' => 'João Pedro', 'password' => Hash::make('password123')]
        );

        $users = [$maria, $carlos, $ana, $joao];

        // 2. Realistic Urban Occurrences centered in Belém do Pará
        $seedOccurrences = [
            [
                'user_id' => $maria->id,
                'type' => Occurrence::TYPE_FLOOD_PRONE,
                'title' => 'Ponto de alagamento crônico na esquina com chuva e maré alta',
                'description' => 'A água da chuva acumula rapidamente com o canal cheio, invadindo a calçada e impedindo o trânsito de pedestres e veículos baixos.',
                'neighborhood' => 'Reduto',
                'address' => 'Travessa Rui Barbosa com Rua Municipalidade',
                'latitude' => -1.449200,
                'longitude' => -48.498500,
                'severity' => 'critical',
                'status' => 'active',
                'photo_url' => 'https://images.unsplash.com/photo-1547683905-f686c993aae5?auto=format&fit=crop&w=800&q=80',
            ],
            [
                'user_id' => $carlos->id,
                'type' => Occurrence::TYPE_PAVEMENT_REPAIR,
                'title' => 'Buraco profundo na faixa da direita próximo à Doca',
                'description' => 'Asfalto cedeu abrindo uma cratera perigosa para motociclistas e ciclistas que trafegam pelo corredor.',
                'neighborhood' => 'Umarizal',
                'address' => 'Rua Domingos Marreiros, altura do n° 450',
                'latitude' => -1.444500,
                'longitude' => -48.486200,
                'severity' => 'high',
                'status' => 'active',
                'photo_url' => 'https://images.unsplash.com/photo-1515162816999-a0c47dc192f7?auto=format&fit=crop&w=800&q=80',
            ],
            [
                'user_id' => $ana->id,
                'type' => Occurrence::TYPE_WASTE_DUMPING,
                'title' => 'Descarte irregular de entulho e podas na calçada histórica',
                'description' => 'Acúmulo clandestino de restos de obra e caixas próximo aos casarões. Mau cheiro e obstrução de pedestres.',
                'neighborhood' => 'Cidade Velha',
                'address' => 'Rua Siqueira Mendes c/ Travessa Joaquim Távora',
                'latitude' => -1.461500,
                'longitude' => -48.504000,
                'severity' => 'medium',
                'status' => 'active',
                'photo_url' => 'https://images.unsplash.com/photo-1605600659908-0ef719419d41?auto=format&fit=crop&w=800&q=80',
            ],
            [
                'user_id' => $joao->id,
                'type' => Occurrence::TYPE_STREET_LIGHTING,
                'title' => 'Postes com lâmpadas apagadas no entorno da praça',
                'description' => 'Trecho muito escuro após as 18h30. Moradores e estudantes relatam sensação de insegurança constante.',
                'neighborhood' => 'Batista Campos',
                'address' => 'Rua dos Mundurucus, quadra em frente à praça',
                'latitude' => -1.459000,
                'longitude' => -48.489500,
                'severity' => 'high',
                'status' => 'active',
                'photo_url' => 'https://images.unsplash.com/photo-1517457373958-b7bdd4587205?auto=format&fit=crop&w=800&q=80',
            ],
            [
                'user_id' => $maria->id,
                'type' => Occurrence::TYPE_UNPAVED_STREET,
                'title' => 'Passagem de terra sem pavimentação e cheia de lamaçais',
                'description' => 'Rua sem drenagem ou pavimentação asfáltica. Quando chove, o tráfego de ambulâncias e carros de entrega é impossibilitado.',
                'neighborhood' => 'Marco',
                'address' => 'Passagem São Pedro, próximo à Travessa Humaitá',
                'latitude' => -1.435000,
                'longitude' => -48.468000,
                'severity' => 'medium',
                'status' => 'active',
                'photo_url' => 'https://images.unsplash.com/photo-1533575770077-052fa2c609fc?auto=format&fit=crop&w=800&q=80',
            ],
            [
                'user_id' => $carlos->id,
                'type' => Occurrence::TYPE_FLOOD_PRONE,
                'title' => 'Enxurrada e boca de lobo entupida na Av. Nazaré',
                'description' => 'A água sobe cerca de 30cm sobre a pista no trecho em frente à escola, obrigando desvio dos carros.',
                'neighborhood' => 'Nazaré',
                'address' => 'Av. Nazaré com Travessa 14 de Março',
                'latitude' => -1.452800,
                'longitude' => -48.481200,
                'severity' => 'critical',
                'status' => 'active',
                'photo_url' => 'https://images.unsplash.com/photo-1547683905-f686c993aae5?auto=format&fit=crop&w=800&q=80',
            ],
            [
                'user_id' => $ana->id,
                'type' => Occurrence::TYPE_PAVEMENT_REPAIR,
                'title' => 'Pavimentação danificada com desnível acentuado',
                'description' => 'A pista cedeu após manutenção da rede de água. Risco de quebra de suspensão e acidentes.',
                'neighborhood' => 'Guamá',
                'address' => 'Rua Barão de Igarapé-Miri, altura do número 780',
                'latitude' => -1.472000,
                'longitude' => -48.455000,
                'severity' => 'medium',
                'status' => 'active',
                'photo_url' => 'https://images.unsplash.com/photo-1515162816999-a0c47dc192f7?auto=format&fit=crop&w=800&q=80',
            ],
            [
                'user_id' => $joao->id,
                'type' => Occurrence::TYPE_WASTE_DUMPING,
                'title' => 'Acúmulo de lixo e caçamba transbordando na via',
                'description' => 'Restos orgânicos e sacolas plásticas espalhadas pelos animais na pista de rolamento.',
                'neighborhood' => 'Pedreira',
                'address' => 'Travessa Antônio Baena c/ Rua Pedro Miranda',
                'latitude' => -1.428000,
                'longitude' => -48.462000,
                'severity' => 'low',
                'status' => 'active',
                'photo_url' => 'https://images.unsplash.com/photo-1605600659908-0ef719419d41?auto=format&fit=crop&w=800&q=80',
            ],
        ];

        foreach ($seedOccurrences as $data) {
            $occ = Occurrence::create($data);

            OccurrenceConfirmation::firstOrCreate([
                'occurrence_id' => $occ->id,
                'user_id' => $occ->user_id,
            ]);

            $confirmCount = 1;
            foreach ($users as $u) {
                if ($u->id !== $occ->user_id && rand(0, 1) === 1) {
                    OccurrenceConfirmation::firstOrCreate([
                        'occurrence_id' => $occ->id,
                        'user_id' => $u->id,
                    ]);
                    $confirmCount++;
                }
            }

            $occ->update(['confirmations_count' => $confirmCount]);

            if (DB::getDriverName() === 'pgsql') {
                DB::statement(
                    'UPDATE occurrences SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [$occ->longitude, $occ->latitude, $occ->id]
                );
            }
        }
    }
}
