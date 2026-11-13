<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/**
 * Cria os parâmetros que ainda não existem. Valores financeiros começam vazios:
 * a Ricarte Prev precisa informar os próprios números antes de os custos
 * estimados aparecerem nos painéis.
 */
class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'routes.road_factor', 'value' => '1.30', 'type' => 'decimal', 'group' => 'rotas',
                'label' => 'Fator de estrada',
                'description' => 'Multiplica a distância em linha reta para estimar a distância rodada.'],
            ['key' => 'routes.cost_per_km', 'value' => null, 'type' => 'decimal', 'group' => 'rotas',
                'label' => 'Custo estimado por km (R$)',
                'description' => 'Usado para estimar o custo de deslocamento antes do lançamento das despesas reais.'],
            ['key' => 'funnel.stale_after_days', 'value' => 7, 'type' => 'int', 'group' => 'funil',
                'label' => 'Dias para considerar uma oportunidade parada',
                'description' => 'Oportunidades sem movimentação por mais dias que isso geram alerta.'],
            ['key' => 'visits.result_due_hours', 'value' => 24, 'type' => 'int', 'group' => 'agenda',
                'label' => 'Prazo para registrar o resultado da visita (horas)',
                'description' => 'Depois desse prazo a visita aparece como "sem resultado registrado".'],
            ['key' => 'finance.margin_revenue_basis', 'value' => 'expected', 'type' => 'string', 'group' => 'financeiro',
                'label' => 'Receita usada na margem',
                'description' => 'expected = receita prevista dos contratos; received = receita efetivamente recebida.'],
            ['key' => 'referrals.attribution_window_days', 'value' => 90, 'type' => 'int', 'group' => 'indicacoes',
                'label' => 'Janela de atribuição da indicação (dias)',
                'description' => 'Uma nova indicação de um contato já existente dentro desse prazo fica com o primeiro indicador.'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::query()->firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
