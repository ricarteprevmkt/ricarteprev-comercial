<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Processos do Meu INSS lidos do C2 Prev. Só o necessário para o comercial:
        // a ligação com o contato (pelo hash do CPF) e o resultado do processo.
        Schema::create('c2_processes', function (Blueprint $table) {
            $table->id();
            $table->string('c2_id', 128)->unique();
            $table->string('c2_client_id', 128)->index();
            $table->string('client_name')->nullable();
            $table->string('cpf_hash', 64)->nullable()->index();
            $table->string('cpf_last_digits', 2)->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('request_number', 60)->nullable();
            $table->string('benefit_type')->nullable();
            $table->string('status_text')->nullable();
            $table->string('decision_text')->nullable();
            $table->string('outcome', 20)->index(); // in_progress|granted|denied|concluded
            $table->date('opened_at')->nullable();
            $table->date('concluded_at')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();
        });

        // Posição da sincronização (cursores do feed). Fica no banco, e não no
        // cache, porque o deploy limpa o cache.
        Schema::create('integration_states', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_states');
        Schema::dropIfExists('c2_processes');
    }
};
