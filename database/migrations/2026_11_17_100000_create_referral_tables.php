<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Executivo que recebe por padrão as indicações deste indicador.
        Schema::table('regional_indicators', function (Blueprint $table) {
            $table->foreignId('default_executive_id')->nullable()->after('region_id')->constrained('executives')->nullOnDelete();
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regional_indicator_id')->constrained('regional_indicators')->restrictOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->restrictOnDelete();
            $table->foreignId('executive_id')->nullable()->constrained('executives')->nullOnDelete();
            $table->foreignId('referral_channel_id')->nullable()->constrained('referral_channels')->nullOnDelete();
            $table->date('referred_at')->index();
            $table->string('status', 20)->index(); // referred|contacted|qualified|opportunity|proposal|signed|lost
            $table->unsignedTinyInteger('furthest_rank')->default(1); // 1 a 6, nunca diminui
            // Indicação de um contato que já existia: fica registrada, mas não muda a atribuição.
            $table->boolean('is_duplicate')->default(false)->index();
            $table->foreignId('duplicate_of_referral_id')->nullable()->constrained('referrals')->nullOnDelete();
            $table->boolean('consent_confirmed')->default(false);
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['regional_indicator_id', 'referred_at']);
        });

        Schema::create('referral_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // null = automático
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['referral_id', 'created_at']);
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regional_indicator_id')->constrained('regional_indicators')->restrictOnDelete();
            $table->foreignId('referral_id')->nullable()->constrained('referrals')->nullOnDelete();
            $table->foreignId('contract_id')->constrained('contracts')->restrictOnDelete();
            $table->foreignId('contract_receipt_id')->nullable()->constrained('contract_receipts')->restrictOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
            // Cópia da regra no momento da geração: mudar a regra depois não altera comissões já geradas.
            $table->string('rule_snapshot', 255);
            $table->decimal('base_amount', 12, 2)->nullable();
            $table->decimal('expected_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->string('status', 15)->index(); // expected|approved|paid|cancelled
            $table->date('generated_at')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'contract_receipt_id', 'regional_indicator_id'], 'commissions_event_unique');
        });

        Schema::create('commission_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained('commissions')->restrictOnDelete();
            $table->date('paid_at')->index();
            $table->decimal('amount', 12, 2);
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payments');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('referral_histories');
        Schema::dropIfExists('referrals');
        Schema::table('regional_indicators', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_executive_id');
        });
    }
};
