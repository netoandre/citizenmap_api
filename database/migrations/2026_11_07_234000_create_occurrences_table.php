<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');
        }

        Schema::create('occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index(); // waste_dumping, unpaved_street, pavement_repair, flood_prone, street_lighting
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('neighborhood', 100)->index();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 8)->index();
            $table->decimal('longitude', 11, 8)->index();
            $table->text('photo_url')->nullable();
            $table->string('status', 30)->default('active')->index(); // active, in_review, resolved
            $table->string('severity', 20)->default('medium'); // low, medium, high, critical
            $table->unsignedInteger('confirmations_count')->default(0)->index();
            $table->timestamps();
        });

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE occurrences ADD COLUMN geom geometry(Point, 4326);');
            DB::statement('CREATE INDEX occurrences_geom_gist ON occurrences USING GIST (geom);');
            DB::statement('
                CREATE OR REPLACE FUNCTION sync_occurrence_geom()
                RETURNS trigger AS $$
                BEGIN
                    NEW.geom := ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326);
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ');
            DB::statement('DROP TRIGGER IF EXISTS trg_sync_occurrence_geom ON occurrences;');
            DB::statement('
                CREATE TRIGGER trg_sync_occurrence_geom
                BEFORE INSERT OR UPDATE OF latitude, longitude
                ON occurrences
                FOR EACH ROW
                EXECUTE FUNCTION sync_occurrence_geom();
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS trg_sync_occurrence_geom ON occurrences;');
            DB::statement('DROP FUNCTION IF EXISTS sync_occurrence_geom();');
        }

        Schema::dropIfExists('occurrences');
    }
};
