<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca as categorias que compõem o indicador "Deslocamentos"
 * (combustível, pedágio, estacionamento). Editável em Catálogos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->boolean('is_travel')->default(false)->after('nature');
        });

        DB::table('expense_categories')
            ->whereIn('slug', ['combustivel-e-deslocamento', 'pedagio', 'estacionamento'])
            ->update(['is_travel' => true]);
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn('is_travel');
        });
    }
};
