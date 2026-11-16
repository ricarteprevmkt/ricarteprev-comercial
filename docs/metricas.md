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
- **Distância da rota:** soma das rotas planejadas do período, pelas estradas (OpenRouteService). Quando o serviço não está disponível, linha reta × fator de estrada, e a tela indica "Distância estimada". É a distância planejada entre as paradas (partindo da cidade base, sem a volta), não a rodada de fato: essa é o km informado pelo executivo ao concluir a visita. Pontos sem endereço marcado usam o centro da cidade e deixam a rota marcada como aproximada.
- **Km informados:** soma do km registrado nas visitas concluídas. Fica sempre separado do estimado.
- **Custo estimado do deslocamento:** distância estimada × custo por km (parâmetro). Sem o parâmetro, não é exibido.
- **Despesas do dia:** despesas efetivas lançadas na data, exceto as recusadas.
- **Visitas sem resultado:** concluídas há mais de `visits.result_due_hours` horas sem resultado registrado.
- **Conversão de visitas em oportunidades:** oportunidades criadas a partir de visitas ÷ visitas realizadas × 100.

## Visão geral e executivos

- **Evolução comercial:** visitas realizadas (no período) e, da coorte do funil, oportunidades, atendimentos agendados, propostas e contratos.
- **Visitas → oportunidades:** oportunidades abertas a partir de uma visita no período ÷ visitas realizadas × 100.
- **Ritmo da meta:** proporção do mês já decorrida (dia de hoje ÷ dias do mês). Em meses encerrados, 100%.
- **Executivo abaixo da meta:** executivo ativo cujo percentual de visitas OU de contratos realizados no mês está abaixo do ritmo esperado. Executivos sem meta não entram.
- **Meta do mês:** a meta específica do mês, se cadastrada; senão, a meta padrão do executivo.
- **Deslocamento do executivo:** km informados nas visitas; sem km informado, a distância estimada da rota.

## Contratos e receita

- **Contratos assinados:** contratos com `signed_at` no período.
- **Receita contratada:** soma de `contract_value` dos contratos assinados no período.
- **Receita prevista:** soma de `expected_revenue`.
- **Receita recebida:** soma de `contract_receipts.amount` com `received_at` no período. Nunca é inferida do contrato.

## Custos

- Entram só despesas **aprovadas** e do tipo **efetivo**. Pendentes aparecem à parte; recusadas nunca entram. Valores estimados (rota × custo por km) aparecem em separado, nunca somados aos efetivos.
- **Custo operacional:** soma das despesas aprovadas do período.
- **Deslocamentos:** despesas aprovadas das categorias marcadas como deslocamento (combustível, pedágio, estacionamento; configurável em Catálogos).
- **Custo por região:** região do executivo da despesa; despesa geral usa a região da cidade informada. Visitas e contratos contam pela região do executivo.
- **Receita prevista:** soma da receita prevista de cada contrato assinado; quando o contrato não tem receita prevista, usa o valor do contrato.
- **Custo por visita:** custos comerciais atribuídos ao período ÷ visitas concluídas no período.
- **Custo por contrato:** custos comerciais atribuídos ao período ÷ contratos assinados no período.
- **Margem de contribuição estimada:** receita considerada − despesas aprovadas de natureza variável − comissões de parceiros geradas no período (exceto canceladas). A receita considerada é a prevista ou a recebida, conforme `finance.margin_revenue_basis`. O card mostra qual base está em uso. Despesas de natureza fixa ficam fora da margem.
- Se o custo da equipe (salários) entra no custo por contrato **(a validar)**: hoje, só se for lançado na categoria "Equipe comercial".

## Indicações

- **Base (coorte):** indicações válidas (não duplicadas) com data no período.
- **Leads qualificados / oportunidades geradas:** indicações da base que chegaram a "Qualificada" / "Oportunidade gerada" (etapa mais avançada alcançada).
- **Contratos originados:** contratos assinados no período com parceiro atribuído.
- **Conversão das indicações:** indicações da base que chegaram a "Contrato assinado" ÷ base × 100.
- **Comissões previstas:** comissões geradas no período ainda não aprovadas. **Aprovadas:** aprovadas no período. **Pagas:** pagamentos registrados no período.
- **Mapa:** círculo no centro de cada município, com tamanho e cor pela quantidade de indicações (faixas 1–7, 8–15, 16–25, mais de 25).

- Produtividade de parceiros é medida separadamente da produtividade dos executivos.
- **Conversão do parceiro:** contratos originados ÷ indicações × 100.
- **Duplicidade:** um contato já existente indicado de novo dentro de `referrals.attribution_window_days` mantém o primeiro parceiro. A nova indicação fica registrada como duplicada, para análise.
- Comissão só existe quando há uma regra ativa associada ao parceiro. Nenhuma indicação gera comissão automaticamente.
