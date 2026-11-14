# Dicionário de métricas

Fonte única das fórmulas. Painéis e relatórios usam os mesmos serviços de cálculo, então o mesmo número aparece igual em todo lugar. Este documento é atualizado a cada fase; itens marcados **(a validar)** dependem da Ricarte Prev.

## Período

- Todo indicador respeita o período selecionado (dias inteiros, fuso America/Fortaleza).
- **Período anterior:** mesma quantidade de dias, terminando na véspera do início. Também é possível comparar com o mesmo período do ano anterior.

## Funil comercial

Etapas, na ordem: Contato → Qualificado → Atendimento agendado → Proposta apresentada → Contrato assinado. Situações fora do funil: Perdido, Desqualificado, Em acompanhamento.

- **Base do funil (coorte):** oportunidades criadas no período, exceto as que estão em "Em acompanhamento".
- **Chegou à etapa X:** oportunidades da base cuja etapa mais avançada já alcançada (`furthest_stage_rank`) é X ou posterior. Uma oportunidade que pula etapas conta nas etapas puladas, e uma oportunidade perdida continua contando nas etapas que alcançou.
- **Conversão entre etapas:** chegaram à etapa seguinte ÷ chegaram à etapa atual × 100.
- **Conversão geral:** chegaram a Contrato assinado ÷ base × 100.
- **Tempo médio de conversão:** média, em dias, entre a abertura da oportunidade e a assinatura, dos contratos assinados no período.
- **Oportunidade parada:** em aberto e sem nenhuma atividade (mudança de etapa, próxima ação, proposta, contrato) há mais dias que o parâmetro `funnel.stale_after_days`.
- **Prazo vencido:** em aberto com prazo da próxima ação anterior a hoje.
- "Em acompanhamento" não entra na base de conversão. **(a validar)**

## Visitas

- **Visitas realizadas:** visitas concluídas com horário agendado no período.
- **Visitas planejadas:** visitas agendadas para o período, exceto canceladas e as substituídas por reagendamento.
- **Visitas pendentes:** agendadas, confirmadas ou em andamento.
- **Distância estimada:** soma das rotas do período (linha reta × fator de estrada). Pontos sem endereço marcado usam o centro da cidade e deixam a rota marcada como aproximada.
- **Km informados:** soma do km registrado nas visitas concluídas. Fica sempre separado do estimado.
- **Custo estimado do deslocamento:** distância estimada × custo por km (parâmetro). Sem o parâmetro, não é exibido.
- **Despesas do dia:** despesas efetivas lançadas na data, exceto as recusadas.
- **Visitas sem resultado:** concluídas há mais de `visits.result_due_hours` horas sem resultado registrado.
- **Conversão de visitas em oportunidades:** oportunidades criadas a partir de visitas ÷ visitas realizadas × 100.

## Contratos e receita

- **Contratos assinados:** contratos com `signed_at` no período.
- **Receita contratada:** soma de `contract_value` dos contratos assinados no período.
- **Receita prevista:** soma de `expected_revenue`.
- **Receita recebida:** soma de `contract_receipts.amount` com `received_at` no período. Nunca é inferida do contrato.

## Custos

- Entram só despesas **aprovadas** e do tipo **efetivo**. Valores estimados aparecem em separado, nunca somados aos efetivos.
- **Custo por visita:** custos comerciais atribuídos ao período ÷ visitas concluídas no período.
- **Custo por contrato:** custos comerciais atribuídos ao período ÷ contratos assinados no período.
- **Margem de contribuição estimada:** receita considerada − custos de natureza variável. A receita considerada é a prevista ou a recebida, conforme `finance.margin_revenue_basis`. O card mostra qual base está em uso.
- Se o custo da equipe (salários) entra no custo por contrato **(a validar)**: hoje, só se for lançado na categoria "Equipe comercial".

## Indicações

- **Base (coorte):** indicações válidas (não duplicadas) com data no período.
- **Leads qualificados / oportunidades geradas:** indicações da base que chegaram a "Qualificada" / "Oportunidade gerada" (etapa mais avançada alcançada).
- **Contratos originados:** contratos assinados no período com indicador atribuído.
- **Conversão das indicações:** indicações da base que chegaram a "Contrato assinado" ÷ base × 100.
- **Comissões previstas:** comissões geradas no período ainda não aprovadas. **Aprovadas:** aprovadas no período. **Pagas:** pagamentos registrados no período.
- **Mapa:** círculo no centro de cada município, com tamanho e cor pela quantidade de indicações (faixas 1–7, 8–15, 16–25, mais de 25).

- Produtividade de indicadores é medida separadamente da produtividade dos executivos.
- **Conversão do indicador:** contratos originados ÷ indicações × 100.
- **Duplicidade:** um contato já existente indicado de novo dentro de `referrals.attribution_window_days` mantém o primeiro indicador. A nova indicação fica registrada como duplicada, para análise.
- Comissão só existe quando há uma regra ativa associada ao indicador. Nenhuma indicação gera comissão automaticamente.
