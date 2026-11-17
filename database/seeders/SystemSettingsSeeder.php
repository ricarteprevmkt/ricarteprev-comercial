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
                'description' => 'Reserva: multiplica a distância em linha reta quando o cálculo pelas estradas não está disponível.'],
            ['key' => 'routes.cost_per_km', 'value' => null, 'type' => 'decimal', 'group' => 'rotas',
                'label' => 'Custo estimado por km (R$)',
                'description' => 'Usado para estimar o custo de deslocamento antes do lançamento das despesas reais.'],
            ['key' => 'funnel.stale_after_days', 'value' => 7, 'type' => 'int', 'group' => 'funil',
                'label' => 'Dias para considerar uma oportunidade parada',
                'description' => 'Oportunidades sem movimentação por mais dias que isso geram alerta.'],
            ['key' => 'funnel.proposal_followup_days', 'value' => 5, 'type' => 'int', 'group' => 'funil',
                'label' => 'Dias para cobrar retorno de proposta',
                'description' => 'Propostas apresentadas há mais dias que isso, sem avanço, geram aviso ao executivo.'],
            ['key' => 'visits.result_due_hours', 'value' => 24, 'type' => 'int', 'group' => 'agenda',
                'label' => 'Prazo para registrar o resultado da visita (horas)',
                'description' => 'Depois desse prazo a visita aparece como "sem resultado registrado".'],
            ['key' => 'finance.margin_revenue_basis', 'value' => 'expected', 'type' => 'string', 'group' => 'financeiro',
                'label' => 'Receita usada na margem',
                'description' => 'Base de receita para calcular a margem de contribuição estimada.'],
            ['key' => 'referrals.attribution_window_days', 'value' => 90, 'type' => 'int', 'group' => 'indicacoes',
                'label' => 'Janela de atribuição da indicação (dias)',
                'description' => 'Uma nova indicação de um contato já existente dentro desse prazo fica com o primeiro parceiro.'],
            ['key' => 'c2.registration_days', 'value' => 2, 'type' => 'int', 'group' => 'c2',
                'label' => 'Dias para o cliente aparecer no C2',
                'description' => 'Contrato assinado há mais dias que isso, sem processo no C2 com o CPF do cliente, gera aviso.'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::query()->firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
