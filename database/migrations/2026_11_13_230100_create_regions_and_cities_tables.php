<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Regiões comerciais definidas pela Ricarte Prev (ex.: um agrupamento de municípios).
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Municípios oficiais do IBGE. "served" indica se a Ricarte atua na cidade.
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ibge_code')->unique();
            $table->string('name', 120);
            $table->char('uf', 2);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->boolean('served')->default(false);
            $table->timestamps();

            $table->index(['uf', 'name']);
            $table->index('served');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('regions');
    }
};
