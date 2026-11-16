<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            // road = pelas estradas (OpenRouteService); estimate = linha reta × fator de estrada.
            $table->string('km_source', 10)->nullable()->after('km_estimated');
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn('km_source');
        });
    }
};
