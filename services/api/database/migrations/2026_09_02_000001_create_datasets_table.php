<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datasets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('external_key', 191)->unique();
            $table->string('name');
            $table->unsignedInteger('srid');
            $table->decimal('precision_grid', 18, 9);
            $table->decimal('area_tolerance', 24, 9)->default(0);
            $table->decimal('minimum_area', 24, 9)->nullable();
            $table->boolean('allow_holes')->default(false);
            $table->boolean('allow_multipolygon')->default(false);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datasets');
    }
};
