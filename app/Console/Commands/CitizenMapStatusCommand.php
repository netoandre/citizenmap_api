<?php

namespace App\Console\Commands;

use App\Models\Occurrence;
use App\Models\User;
use App\Services\RouteAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CitizenMapStatusCommand extends Command
{
    protected $signature = 'citizenmap:status';
    protected $description = 'Displays CitizenMap system status, spatial driver, and data statistics';

    public function handle(RouteAnalysisService $routeService): int
    {
        $this->info('=== CitizenMap System Status ===');

        $driver = DB::getDriverName();
        $this->line("Database Driver: <comment>{$driver}</comment>");

        if ($driver === 'pgsql') {
            try {
                $postgisVer = DB::select('SELECT PostGIS_Version() AS ver')[0]->ver ?? 'Not installed';
                $this->line("PostGIS Extension: <info>ACTIVE (Version {$postgisVer})</info>");
            } catch (\Throwable $e) {
                $this->warn("PostGIS Extension check failed: " . $e->getMessage());
            }
        } else {
            $this->line("Spatial Engine: <comment>Internal Geometric Engine (SQLite/Portability Mode)</comment>");
            $this->line("To use native PostGIS: set DB_CONNECTION=pgsql in .env and run migrations.");
        }

        $usersCount = User::count();
        $occurrencesCount = Occurrence::count();
        $this->line("Registered Citizens: <info>{$usersCount}</info>");
        $this->line("Active Occurrences: <info>{$occurrencesCount}</info>");

        $this->newLine();
        $this->info('Occurrences by Type:');
        $types = Occurrence::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type');

        foreach ($types as $type => $count) {
            $this->line(" - {$type}: {$count}");
        }

        $this->newLine();
        $this->info('Testing Route Hazard Conflict Analysis:');
        // Route between Reduto, Umarizal and Nazaré in Belém
        $testRoute = [
            [-48.498500, -1.449200], // Start at Reduto
            [-48.486200, -1.444500], // Via Umarizal / Doca
            [-48.481200, -1.452800], // Arrive Nazaré
        ];

        $analysis = $routeService->analyzeRoute($testRoute, 50.0);
        $this->line("Test Route Conflicts Detected: <comment>{$analysis['total_conflicts']}</comment>");
        $this->line("Recommendation: <info>{$analysis['recommendation']['title_pt']}</info> ({$analysis['recommendation']['level']})");

        return 0;
    }
}
