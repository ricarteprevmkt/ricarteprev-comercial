<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Atividade de agenda: visita externa, atendimento, retorno, follow-up ou reunião.
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('executive_id')->constrained('executives')->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->restrictOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->restrictOnDelete();
            $table->string('contact_name')->nullable(); // quando ainda não há contato cadastrado
            $table->string('type', 20);     // field_visit|appointment|return|follow_up|meeting
            $table->string('priority', 10)->default('normal'); // low|normal|high
            $table->string('status', 20)->index(); // scheduled|confirmed|in_progress|done|not_done|cancelled|rescheduled
            $table->dateTime('scheduled_at')->index();
            $table->string('address')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('neighborhood', 120)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_source', 10)->nullable(); // manual|lead|city (city = aproximada)
            $table->string('notes', 1000)->nullable();

            // Registro da visita
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->text('outcome')->nullable();
            $table->foreignId('not_done_reason_id')->nullable()->constrained('visit_reasons')->restrictOnDelete();
            $table->string('next_action')->nullable();
            $table->date('next_contact_at')->nullable();
            $table->decimal('km_reported', 8, 1)->nullable();
            $table->decimal('checkin_latitude', 10, 7)->nullable();
            $table->decimal('checkin_longitude', 10, 7)->nullable();
            $table->dateTime('checkin_at')->nullable();
            $table->foreignId('rescheduled_to_id')->nullable()->constrained('visits')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['executive_id', 'scheduled_at']);
        });

        // Rota estimada do dia de um executivo (recalculada quando a agenda do dia muda).
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('executive_id')->constrained('executives')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('km_estimated', 8, 1)->default(0);
            $table->unsignedSmallInteger('stops_count')->default(0);
            $table->boolean('has_approximate_points')->default(false);
            $table->timestamps();

            $table->unique(['executive_id', 'date']);
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->foreignId('visit_id')->unique()->constrained('visits')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->decimal('leg_km', 8, 1)->nullable(); // do ponto anterior até esta parada
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('spent_on')->index();
            $table->foreignId('executive_id')->nullable()->constrained('executives')->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('visits')->nullOnDelete();
            $table->string('kind', 10)->default('actual'); // actual|estimated
            $table->string('status', 10)->default('pending')->index(); // pending|approved|rejected
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['executive_id', 'spent_on']);
        });

        // Arquivos enviados (comprovantes etc.), sempre no disco privado.
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Oportunidade aberta a partir de uma visita (conversão visita → oportunidade).
        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('visit_id')->nullable()->after('regional_indicator_id')->constrained('visits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visit_id');
        });
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('visits');
    }
};
