<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class InitPostGisCommand extends Command
{
    protected $signature = 'citizenmap:init-postgis 
                            {--host=127.0.0.1 : PostgreSQL Host}
                            {--port=5432 : PostgreSQL Port}
                            {--user=postgres : PostgreSQL User}
                            {--password=password : PostgreSQL Password}
                            {--database=citizenmap : Database Name}';

    protected $description = 'Initializes PostgreSQL PostGIS database and runs spatial migrations';

    public function handle(): int
    {
        $host = $this->option('host');
        $port = $this->option('port');
        $user = $this->option('user');
        $password = $this->option('password');
        $dbName = $this->option('database');

        $this->info("Conectando ao PostgreSQL em {$host}:{$port}...");

        try {
            $pdo = new PDO("pgsql:host={$host};port={$port};dbname=postgres", $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // Check if DB exists
            $stmt = $pdo->prepare("SELECT 1 FROM pg_database WHERE datname = ?");
            $stmt->execute([$dbName]);
            if (!$stmt->fetch()) {
                $this->line("Criando banco de dados <info>{$dbName}</info>...");
                $pdo->exec("CREATE DATABASE \"{$dbName}\"");
            } else {
                $this->line("Banco de dados <info>{$dbName}</info> já existe.");
            }

            // Connect to database and enable postgis
            $dbPdo = new PDO("pgsql:host={$host};port={$port};dbname={$dbName}", $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $this->line("Habilitando extensão PostGIS...");
            $dbPdo->exec("CREATE EXTENSION IF NOT EXISTS postgis;");

            $version = $dbPdo->query("SELECT PostGIS_Version()")->fetchColumn();
            $this->info("✓ Extensão PostGIS habilitada com sucesso! Versão: {$version}");

            $this->newLine();
            $this->info("Executando migrações e seeders espaciais...");
            config(['database.default' => 'pgsql']);
            config(['database.connections.pgsql.host' => $host]);
            config(['database.connections.pgsql.port' => $port]);
            config(['database.connections.pgsql.database' => $dbName]);
            config(['database.connections.pgsql.username' => $user]);
            config(['database.connections.pgsql.password' => $password]);

            DB::purge('pgsql');

            $this->call('migrate:fresh', ['--force' => true]);
            $this->call('db:seed', ['--force' => true]);

            $this->info("✓ CitizenMap configurado com PostGIS com sucesso!");

            return 0;
        } catch (\Throwable $e) {
            $this->error("Erro ao inicializar PostGIS: " . $e->getMessage());
            $this->line("Dica: Inicie o contêiner Docker PostGIS com 'docker compose up -d' ou verifique o serviço local do PostgreSQL.");
            return 1;
        }
    }
}
