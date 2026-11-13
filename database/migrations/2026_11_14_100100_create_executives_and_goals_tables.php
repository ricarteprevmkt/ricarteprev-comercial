<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->text('cpf_encrypted')->nullable();
            $table->char('cpf_hash', 64)->nullable()->unique();
            $table->string('photo_path')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->date('hired_at')->nullable();
            $table->string('status', 20)->default('active')->index(); // active|on_leave|terminated
            $table->unsignedSmallInteger('monthly_visits_target')->nullable();
            $table->unsignedSmallInteger('monthly_contracts_target')->nullable();
            $table->decimal('cost_allowance', 12, 2)->nullable();
            $table->text('compensation_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Meta de um mês específico. Sem registro, vale a meta padrão do executivo.
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('executive_id')->constrained('executives')->cascadeOnDelete();
            $table->date('month'); // sempre o dia 1
            $table->unsignedSmallInteger('visits_target');
            $table->unsignedSmallInteger('contracts_target');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['executive_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
        Schema::dropIfExists('executives');
    }
};
