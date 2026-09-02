<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('dataset_id');
            $table->text('external_id');
            $table->unsignedBigInteger('revision')->default(1);
            $table->jsonb('properties')->default(DB::raw("'{}'::jsonb"));
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->foreign('dataset_id')->references('id')->on('datasets')->cascadeOnDelete();
            $table->unique(['dataset_id', 'external_id']);
            $table->index(['dataset_id', 'active']);
        });

        // SRID is dataset-specific, so the typmod constrains geometry type/dimension but not one global SRID.
        DB::statement('ALTER TABLE parcels ADD COLUMN geom geometry(Polygon) NOT NULL');
        DB::statement('CREATE INDEX parcels_geom_gist ON parcels USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
