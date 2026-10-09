<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('objectid')->nullable();
            $table->string('kdprov', 10)->nullable();
            $table->string('kdkab', 10)->nullable();
            $table->string('kdkec', 10)->nullable();
            $table->string('kddesa', 10)->nullable();
            $table->string('kdsls', 10)->nullable();
            $table->string('nmprov', 100)->nullable();
            $table->string('nmkab', 100)->nullable();
            $table->string('nmkec', 100)->nullable()->index();
            $table->string('nmdesa', 100)->nullable()->index();
            $table->string('nmsubsls', 150)->nullable();
            $table->string('idsls', 30)->nullable()->index();
            $table->string('iddesa', 30)->nullable();
            $table->string('nmsls', 150)->nullable();
            $table->string('idsubsls', 40)->unique();
            $table->string('jenis', 50)->nullable();
            $table->string('rw', 20)->nullable()->index();
            $table->string('rt', 20)->nullable()->index();
            $table->longText('geometry')->nullable(); // Stores GeoJSON geometry JSON
            $table->timestamps();
        });

        Schema::create('region_neighbors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->foreignId('neighbor_id')->constrained('regions')->cascadeOnDelete();
            $table->unsignedSmallInteger('shared_points_count')->default(1);
            $table->timestamps();

            $table->unique(['region_id', 'neighbor_id']);
            $table->index(['neighbor_id', 'region_id']);
        });

        Schema::create('changed_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->unique()->constrained('regions')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('change_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id_a')->constrained('regions')->cascadeOnDelete();
            $table->foreignId('region_id_b')->constrained('regions')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['region_id_a', 'region_id_b']);
            $table->index(['region_id_b', 'region_id_a']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('change_relations');
        Schema::dropIfExists('changed_regions');
        Schema::dropIfExists('region_neighbors');
        Schema::dropIfExists('regions');
    }
};
