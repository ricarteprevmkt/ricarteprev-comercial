<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Interessados: contatos crus (WhatsApp do C2) antes do funil. Só viram
        // contato e oportunidade quando alguém qualifica.
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20);
            // DDD + 8 últimos dígitos: o WhatsApp às vezes guarda o celular sem o 9.
            $table->string('phone_key', 10)->unique();
            $table->string('origin', 60)->nullable();
            $table->text('demand')->nullable();
            $table->text('notes')->nullable();
            $table->string('c2_owner', 120)->nullable();
            $table->string('c2_stage', 60)->nullable();
            $table->date('entered_at')->index();
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('executive_id')->nullable()->constrained('executives')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('discard_reason', 40)->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('discarded_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_imported_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'executive_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
