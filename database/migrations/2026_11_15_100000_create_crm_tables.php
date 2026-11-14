<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contato (pessoa física ou jurídica). Um contato pode ter várias oportunidades.
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('type', 2)->default('pf'); // pf|pj
            $table->string('name');
            $table->text('document_encrypted')->nullable(); // CPF ou CNPJ, criptografado
            $table->char('document_hash', 64)->nullable()->unique();
            $table->string('phone', 20)->nullable()->index();
            $table->string('whatsapp', 20)->nullable()->index();
            $table->string('email')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('neighborhood', 120)->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('lead_source_id')->nullable()->constrained('lead_sources')->restrictOnDelete();
            $table->foreignId('executive_id')->nullable()->constrained('executives')->restrictOnDelete();
            $table->foreignId('regional_indicator_id')->nullable()->constrained('regional_indicators')->restrictOnDelete();
            $table->date('entered_at');
            $table->string('status', 20)->default('active')->index(); // active|client|inactive
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index(['executive_id', 'status']);
            $table->index('entered_at');
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->restrictOnDelete();
            $table->foreignId('executive_id')->constrained('executives')->restrictOnDelete();
            $table->foreignId('lead_source_id')->nullable()->constrained('lead_sources')->restrictOnDelete();
            $table->foreignId('regional_indicator_id')->nullable()->constrained('regional_indicators')->restrictOnDelete();
            $table->string('stage', 30)->index();
            // Etapa mais avançada do funil já alcançada (1 a 5). Base das conversões.
            $table->unsignedTinyInteger('furthest_stage_rank')->default(1);
            $table->string('interest')->nullable();
            $table->decimal('estimated_value', 12, 2)->nullable();
            $table->string('next_action')->nullable();
            $table->date('next_action_due')->nullable()->index();
            $table->foreignId('loss_reason_id')->nullable()->constrained('loss_reasons')->restrictOnDelete();
            $table->string('loss_note', 500)->nullable();
            $table->timestamp('stage_changed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['executive_id', 'stage']);
            $table->index('created_at');
        });

        // Toda movimentação de etapa fica registrada e nunca é apagada.
        Schema::create('opportunity_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('opportunities')->restrictOnDelete();
            $table->string('from_stage', 30)->nullable();
            $table->string('to_stage', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['opportunity_id', 'created_at']);
            $table->index(['to_stage', 'created_at']);
        });

        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('opportunities')->restrictOnDelete();
            $table->date('presented_at');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('description', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('presented_at');
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->unique()->constrained('opportunities')->restrictOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->restrictOnDelete();
            $table->foreignId('executive_id')->constrained('executives')->restrictOnDelete();
            $table->foreignId('lead_source_id')->nullable()->constrained('lead_sources')->restrictOnDelete();
            $table->foreignId('regional_indicator_id')->nullable()->constrained('regional_indicators')->restrictOnDelete();
            $table->string('number', 50)->nullable()->unique();
            $table->string('status', 20)->index(); // pending|signed|cancelled
            $table->date('signed_at')->nullable()->index();
            // Valor do contrato e receita prevista. Receita recebida fica em contract_receipts.
            $table->decimal('contract_value', 12, 2);
            $table->decimal('expected_revenue', 12, 2)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['executive_id', 'signed_at']);
        });

        Schema::create('contract_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->restrictOnDelete();
            $table->date('received_at')->index();
            $table->decimal('amount', 12, 2);
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_receipts');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('proposals');
        Schema::dropIfExists('opportunity_histories');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('leads');
    }
};
