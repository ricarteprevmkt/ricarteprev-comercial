<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dados para o acompanhamento administrativo (só consulta): exigência,
        // agendamento, motivo da decisão, prazo de recurso e situação do
        // monitoramento no C2. E o escopo: protocolos antigos ficam de fora.
        Schema::table('c2_processes', function (Blueprint $table) {
            $table->string('benefit_number', 60)->nullable()->after('request_number');
            $table->string('service')->nullable()->after('benefit_type');
            $table->text('requirement_text')->nullable()->after('decision_text');
            $table->date('requirement_deadline')->nullable()->index()->after('requirement_text');
            $table->date('appointment_date')->nullable()->index()->after('requirement_deadline');
            $table->string('appointment_time', 10)->nullable()->after('appointment_date');
            $table->string('appointment_location')->nullable()->after('appointment_time');
            $table->text('decision_reason')->nullable()->after('appointment_location');
            $table->date('appeal_deadline')->nullable()->index()->after('decision_reason');
            $table->string('monitoring_state', 30)->nullable()->index()->after('appeal_deadline');
            $table->string('monitoring_action', 40)->nullable()->after('monitoring_state');
            $table->timestamp('checked_at')->nullable()->after('concluded_at');
            $table->boolean('in_scope')->default(true)->index()->after('lead_id');
        });

        // Relê todos os processos na próxima sincronização para preencher os campos novos.
        DB::table('integration_states')->whereIn('key', ['c2.backfill_done', 'c2.backfill_cursor', 'c2.backfill_started_at'])->delete();
    }

    public function down(): void
    {
        Schema::table('c2_processes', function (Blueprint $table) {
            $table->dropColumn([
                'benefit_number', 'service', 'requirement_text', 'requirement_deadline', 'appointment_date', 'appointment_time',
                'appointment_location', 'decision_reason', 'appeal_deadline', 'monitoring_state', 'monitoring_action', 'checked_at', 'in_scope',
            ]);
        });
    }
};
