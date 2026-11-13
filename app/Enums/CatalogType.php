<?php

namespace App\Enums;

use App\Models\Catalogs\CatalogItem;
use App\Models\Catalogs\ExpenseCategory;
use App\Models\Catalogs\LeadSource;
use App\Models\Catalogs\LossReason;
use App\Models\Catalogs\ReferralChannel;
use App\Models\Catalogs\VisitReason;

/**
 * Catálogos editáveis na tela de Catálogos. O valor do caso é usado na URL.
 */
enum CatalogType: string
{
    case LeadSources = 'origens';
    case LossReasons = 'motivos-de-perda';
    case VisitReasons = 'motivos-de-visita';
    case ExpenseCategories = 'categorias-de-despesa';
    case ReferralChannels = 'canais-de-indicacao';

    /** @return class-string<CatalogItem> */
    public function model(): string
    {
        return match ($this) {
            self::LeadSources => LeadSource::class,
            self::LossReasons => LossReason::class,
            self::VisitReasons => VisitReason::class,
            self::ExpenseCategories => ExpenseCategory::class,
            self::ReferralChannels => ReferralChannel::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LeadSources => 'Origens de contato',
            self::LossReasons => 'Motivos de encerramento',
            self::VisitReasons => 'Motivos de visita não realizada',
            self::ExpenseCategories => 'Categorias de despesa',
            self::ReferralChannels => 'Canais de indicação',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::LeadSources => 'De onde vêm os contatos. Aparece no cadastro de leads e no gráfico de origem das oportunidades.',
            self::LossReasons => 'Por que uma oportunidade ou indicação foi encerrada sem contrato.',
            self::VisitReasons => 'Por que uma visita agendada não aconteceu.',
            self::ExpenseCategories => 'Classificação das despesas. Categorias variáveis entram na margem de contribuição.',
            self::ReferralChannels => 'Como a indicação chegou: moradores, clientes, parceiros locais, redes sociais.',
        };
    }

    /**
     * Itens padrão sugeridos pelo briefing comercial. São configuração, não dados
     * fictícios: o administrador pode renomear, reordenar ou desativar.
     *
     * @return list<array<string, string>>
     */
    public function defaults(): array
    {
        $names = match ($this) {
            self::LeadSources => ['Visitas externas', 'Indicações', 'Instagram', 'Site', 'WhatsApp', 'Parceiros', 'Outros'],
            self::LossReasons => ['Ausência de interesse', 'Contato não localizado', 'Requisitos não atendidos', 'Desistência do cliente', 'Outros'],
            self::VisitReasons => ['Ausência do contato', 'Reagendamento', 'Cancelamento', 'Impedimento operacional', 'Outros'],
            self::ExpenseCategories => ['Combustível e deslocamento', 'Alimentação', 'Hospedagem', 'Pedágio', 'Estacionamento', 'Equipe comercial', 'Marketing', 'Ferramentas', 'Material de apoio', 'Despesas operacionais', 'Outros'],
            self::ReferralChannels => ['Moradores da região', 'Indicação de clientes', 'Parceiros locais', 'Redes sociais', 'Outros'],
        };

        $fixed = ['Equipe comercial', 'Ferramentas', 'Despesas operacionais'];

        return array_map(fn (string $name) => match ($this) {
            self::ExpenseCategories => ['name' => $name, 'nature' => in_array($name, $fixed, true) ? 'fixed' : 'variable'],
            self::LossReasons => ['name' => $name, 'applies_to' => 'both'],
            default => ['name' => $name],
        }, $names);
    }
}
