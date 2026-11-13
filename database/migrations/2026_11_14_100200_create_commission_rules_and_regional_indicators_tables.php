<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('type', 10); // fixed|percent
            $table->decimal('value', 12, 2);
            $table->string('base', 20)->nullable(); // percent: contract_value|expected_revenue|received_revenue
            $table->string('trigger_event', 30); // contract_signed|revenue_received
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('regional_indicators', function (Blueprint $table) {
            $table->id();
            // Acesso ao sistema é opcional: muitos indicadores só repassam contatos.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('photo_path')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable()->index();
            $table->string('email')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('neighborhood', 120)->nullable();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('referral_channel_id')->nullable()->constrained('referral_channels')->nullOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
            $table->date('registered_at');
            $table->string('status', 20)->default('active')->index(); // active|inactive
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regional_indicators');
        Schema::dropIfExists('commission_rules');
    }
};
