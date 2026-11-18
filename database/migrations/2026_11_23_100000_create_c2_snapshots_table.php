<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Totais do escritório lidos do C2 por uma tarefa agendada (financeiro,
        // judicial, prazos, clientes e leads). Só números: nenhum dado pessoal.
        Schema::create('c2_snapshots', function (Blueprint $table) {
            $table->id();
            $table->json('metrics');
            $table->timestamp('collected_at')->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('c2_snapshots');
    }
};
