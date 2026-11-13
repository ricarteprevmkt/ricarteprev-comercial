# Dicionário de métricas

Fonte única das fórmulas. Painéis e relatórios usam os mesmos serviços de cálculo, então o mesmo número aparece igual em todo lugar. Este documento é atualizado a cada fase; itens marcados **(a validar)** dependem da Ricarte Prev.

## Período

- Todo indicador respeita o período selecionado (dias inteiros, fuso America/Fortaleza).
- **Período anterior:** mesma quantidade de dias, terminando na véspera do início. Também é possível comparar com o mesmo período do ano anterior.

## Funil comercial

Etapas, na ordem: Contato → Qualificado → Atendimento agendado → Proposta apresentada → Contrato assinado. Situações fora do funil: Perdido, Desqualificado, Em acompanhamento.

- **Base do funil (coorte):** oportunidades criadas no período.
- **Chegou à etapa X:** oportunidades da base que passaram pela etapa X em algum momento, segundo `opportunity_histories`. Uma oportunidade que pula etapas conta nas etapas puladas.
- **Conversão entre etapas:** chegaram à etapa seguinte ÷ chegaram à etapa atual × 100.
- **Conversão geral:** chegaram a Contrato assinado ÷ base × 100.
- **Tempo médio de conversão:** média, em dias, entre a criação e a assinatura, das oportunidades assinadas no período.
- "Em acompanhamento" não entra na base de conversão. **(a validar)**

## Visitas

- **Visitas realizadas:** visitas com status Concluída e data efetiva no período.
- **Visitas planejadas:** todas as visitas agendadas para o período, exceto as canceladas.
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

- Produtividade de indicadores é medida separadamente da produtividade dos executivos.
- **Conversão do indicador:** contratos originados ÷ indicações × 100.
- **Duplicidade:** um contato já existente indicado de novo dentro de `referrals.attribution_window_days` mantém o primeiro indicador. A nova indicação fica registrada como duplicada, para análise.
- Comissão só existe quando há uma regra ativa associada ao indicador. Nenhuma indicação gera comissão automaticamente.
