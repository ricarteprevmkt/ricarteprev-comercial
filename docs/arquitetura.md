# Arquitetura do Sistema Comercial Ricarte Prev

## Stack

| Camada | Escolha |
|---|---|
| Backend | Laravel 13, PHP 8.3+ |
| Banco | MySQL 8 (MariaDB compatível) |
| Interface | Blade + Livewire 4 + Flux (componentes gratuitos) + Tailwind CSS 4 |
| Autenticação | Laravel Fortify: login com limite de 5 tentativas por minuto, redefinição de senha, verificação em duas etapas e chaves de acesso. Sem cadastro público |
| Permissões | `spatie/laravel-permission` (perfis e permissões no banco, editáveis pelo administrador) + Gate/Policies |
| Auditoria | `spatie/laravel-activitylog`, tabela `activity_logs` |
| Relatórios | `barryvdh/laravel-dompdf` (PDF) e `openspout/openspout` (Excel/CSV) |
| Gráficos e mapas | ApexCharts e Leaflet + OpenStreetMap, compilados pelo Vite (a partir da Fase 4) |
| Testes | Pest 4 sobre MySQL (`ricarteprev_testing`), Larastan nível 7, Pint |

## Perfis

| Perfil | Acesso padrão |
|---|---|
| Administrador | Tudo (via `Gate::before`) |
| Gestor comercial | Equipe inteira, aprovação de despesas, indicadores, relatórios e metas |
| Executivo comercial | Apenas os próprios registros: agenda, oportunidades, contratos e despesas |
| Indicador regional | Registrar indicações e acompanhar as próprias. Desempenho e comissões só se o administrador liberar |

O catálogo de permissões está em `app/Support/Acl.php`. O seeder aplica os padrões só na criação do perfil; depois, vale o que o administrador configurar.

A permissão de rota controla o acesso ao módulo. O recorte por dono do registro ("o executivo só vê o que é dele") fica nas Policies de cada modelo, a partir da Fase 3.

## Organização do código

```
app/
  Enums/Role.php               perfis
  Support/Acl.php              catálogo de permissões
  Support/Period.php           período de análise (filtro único de todos os painéis)
  Support/Navigation.php       menu por perfil e página inicial após o login
  Http/Middleware/             can.any (qualquer permissão) e sessão de usuário desativado
  Livewire/                    seletor de período, notificações e, nas próximas fases, as telas
  Models/
database/
  data/municipios.csv          5.571 municípios do IBGE com coordenadas
  seeders/                     referência (seguros para produção) e LocalDevSeeder (só local)
docs/                          arquitetura, métricas e implantação
scripts/deploy.sh              deploy na Hostinger via SSH
```

Nas próximas fases, as regras de negócio ficam em classes de ação e serviços de métricas por módulo (`app/Domain/<Módulo>`), reaproveitados pelos painéis e pelos relatórios.

## Relação com o C2 Prev

O C2 Prev continua sendo usado pela Ricarte. Decisão confirmada: este sistema **exporta** dados para o C2; nada é importado de lá. A exportação aguarda a documentação da API e o layout de saída do C2 (pendência 1 em [pendencias.md](pendencias.md)).

## Modelo de dados

### Já criado (Fase 1)

| Tabela | Finalidade |
|---|---|
| `users` | Acesso. Campos extras: `phone`, `active`, `last_login_at`, `deleted_at` |
| `roles`, `permissions`, `model_has_*`, `role_has_permissions` | Perfis e permissões |
| `regions` | Regiões comerciais definidas pela Ricarte |
| `cities` | Municípios do IBGE (`ibge_code` único), coordenadas, região e marcação de cidade atendida |
| `system_settings` | Parâmetros comerciais (fator de estrada, custo por km, prazos de alerta, base da margem, janela de atribuição) |
| `activity_logs` | Auditoria de alterações, logins e tentativas falhas |
| `notifications` | Notificações internas |
| `sessions`, `cache`, `jobs`, `failed_jobs`, `passkeys` | Infraestrutura |

### Criado na Fase 2

| Tabela | Finalidade |
|---|---|
| `executives` | Dados comerciais do executivo, ligado 1:1 a um usuário. CPF criptografado (`cpf_encrypted`) com hash único (`cpf_hash`). Status `active`, `on_leave` ou `terminated`; desligado perde o login automaticamente |
| `goals` | Meta de um mês específico (`UNIQUE executive_id, month`). Sem registro, vale a meta padrão do executivo |
| `regional_indicators` | Indicador regional. Acesso ao sistema opcional (`user_id`). WhatsApp normalizado e sem repetição |
| `commission_rules` | Valor fixo ou percentual (com base de cálculo) e evento que gera o direito |
| `lead_sources`, `loss_reasons`, `visit_reasons`, `expense_categories`, `referral_channels` | Catálogos configuráveis. Itens são desativados, nunca excluídos. Categorias de despesa têm natureza variável ou fixa |

Fotos ficam em `storage/app/private/photos` e são servidas pela rota autenticada `fotos/...`, que confere se o usuário pode ver o cadastro.

### Criado na Fase 3

| Tabela | Finalidade |
|---|---|
| `leads` | Contato (PF ou PJ). CPF/CNPJ criptografado com hash único; telefone e WhatsApp só com dígitos. Origem, executivo responsável e indicador ("Indicado por") |
| `opportunities` | Etapa atual, `furthest_stage_rank` (etapa mais avançada já alcançada, base das conversões), interesse, valor estimado, próxima ação e prazo, motivo de perda, `last_activity_at` para o alerta de oportunidade parada |
| `opportunity_histories` | Cada mudança de etapa: de, para, usuário, observação e data. Nunca é apagado |
| `proposals` | Propostas apresentadas. Registrar uma proposta avança a oportunidade para "Proposta apresentada" |
| `contracts` | Um por oportunidade. Situação: aguardando conclusão, assinado ou cancelado. Copia origem, executivo e indicador da oportunidade |
| `contract_receipts` | Receita efetivamente recebida, lançada só em contrato assinado |

Regras principais (em `app/Actions/Crm`):

- "Contrato assinado" só é alcançado registrando o contrato; depois disso a etapa não muda mais.
- Perdido e Desqualificado exigem motivo; reabrir limpa o motivo.
- Contato duplicado: mesmo CPF/CNPJ é bloqueado; mesmo telefone é bloqueado até o usuário confirmar que é outra pessoa.
- O executivo vê e altera só os próprios registros (policies `LeadPolicy`, `OpportunityPolicy`, `ContractPolicy` e escopo `visibleTo`).

As métricas do funil ficam em `app/Metrics/FunnelMetrics.php` e seguem [metricas.md](metricas.md).

### Criado na Fase 4

| Tabela | Finalidade |
|---|---|
| `visits` | Atividade de agenda (visita externa, atendimento agendado, retorno, follow-up, reunião), com prioridade, situação, local (ponto marcado, ponto do contato ou centro da cidade), registro do resultado, check-in, km informado e vínculo de reagendamento |
| `routes` / `route_stops` | Rota estimada do dia por executivo, recalculada sempre que a agenda do dia muda |
| `expenses` | Despesas efetivas ou estimadas, aguardando aprovação, aprovadas ou recusadas |
| `attachments` | Comprovantes no disco privado, entregues por rota autenticada |
| `opportunities.visit_id` | Oportunidade aberta a partir de uma visita (conversão visita → oportunidade) |

Regras principais (em `app/Actions/Agenda`):

- Agendar um "Atendimento agendado" para uma oportunidade que ainda não chegou a essa etapa avança a oportunidade.
- Concluir uma visita ligada a uma oportunidade registra a atividade e copia a próxima ação e a data do próximo contato.
- Reagendar cria uma nova visita e marca a original como reagendada, mantendo o vínculo.
- A rota parte do centro da cidade base do executivo, segue a ordem dos horários e não inclui a volta.
- Check-in usa a localização do navegador, só com permissão do usuário. Não há rastreamento contínuo.
- O mapa usa Leaflet com tiles do OpenStreetMap (configuráveis por `MAP_TILE_URL`), sem chave de API.

### Criado na Fase 5

| Tabela | Finalidade |
|---|---|
| `referrals` | Indicação: indicador, contato, executivo, canal, data, situação, etapa mais avançada (`furthest_rank`), duplicidade e consentimento |
| `referral_histories` | Cada mudança de situação, manual (com usuário) ou automática (pelo funil) |
| `commissions` | Comissão gerada por contrato ou recebimento, com cópia da regra no momento da geração, valor previsto e aprovado |
| `commission_payments` | Pagamentos (parciais ou total) de uma comissão aprovada |
| `regional_indicators.default_executive_id` | Executivo que recebe as indicações do indicador |

Regras principais (em `app/Actions/Referrals`):

- Contato novo: a indicação cria o contato com origem "Indicações" e "Indicado por" o indicador.
- Contato já existente: a indicação é registrada como duplicada e não muda a atribuição. Exceção: se a indicação válida anterior passou da janela de atribuição (90 dias por padrão) e o contato ainda não é cliente, a nova indicação assume a atribuição.
- A situação acompanha o funil: Contato → Contato realizado, Qualificado → Qualificada, Atendimento agendado → Oportunidade gerada, Proposta → Proposta apresentada, Contrato → Contrato assinado. Se todas as oportunidades forem perdidas, fica Perdida. Visita concluída sem oportunidade conta como Contato realizado.
- Comissão só existe com regra ativa associada ao indicador. O evento da regra define quando gera; a base define sobre o quê (receita recebida gera a cada recebimento). Valores calculados com bcmath e arredondados ao centavo.
- O indicador com acesso vê só as próprias indicações (nome, cidade, data e situação). Desempenho e comissões só aparecem se o administrador liberar.

### Criado na Fase 6

- `expense_categories.is_travel`: categorias que compõem o indicador Deslocamentos.
- Aprovação e recusa de despesas (`app/Actions/Finance/ReviewExpense.php`): só pendentes, ninguém aprova a própria despesa, recusa exige motivo e avisa o executivo.
- `app/Metrics/FinanceMetrics.php`: custos, receitas, margem, distribuição por categoria, canal, região, executivo e evolução mensal.
- Gráficos com ApexCharts (`resources/js/charts.js`), compilados pelo Vite.

### Próximas fases

```
leads(name, type, cpf_cnpj_enc, cpf_cnpj_hash UNIQUE NULL, phone_norm INDEX, whatsapp, email, city_id, neighborhood,
      address, lat, lng, lead_source_id, executive_id, origin_referral_id NULL, entered_at, status)
opportunities(lead_id, executive_id, lead_source_id, stage, interest, estimated_value DECIMAL,
      next_action, next_action_due, loss_reason_id, stage_changed_at, closed_at)
opportunity_histories(opportunity_id, from_stage, to_stage, user_id, note, created_at)
contracts(opportunity_id UNIQUE, lead_id, executive_id, number, signed_at, contract_value DECIMAL,
      expected_revenue DECIMAL, lead_source_id, regional_indicator_id NULL, status)
contract_receipts(contract_id, received_at, amount DECIMAL)                       receita efetivamente recebida

visits(executive_id, lead_id, opportunity_id, type, priority, status, scheduled_at, address, city_id, neighborhood,
      lat, lng, started_at, finished_at, outcome, not_done_reason_id, next_action, next_contact_at, km_reported)
routes(executive_id, date, km_estimated)                                          UNIQUE(executive_id, date)
route_stops(route_id, visit_id UNIQUE, sequence, leg_km_estimated)
expenses(description, expense_category_id, amount DECIMAL, spent_on, executive_id, city_id, region_id, visit_id,
      kind[estimated|actual], status[pending|approved|rejected], created_by, approved_by)

referrals(regional_indicator_id, lead_id, executive_id, referral_channel_id, referred_at, status,
      is_duplicate, duplicate_of_referral_id)
referral_histories(referral_id, from_status, to_status, user_id, note)
commission_rules(name, type[fixed|percent], value DECIMAL, trigger_event, active)
commissions(referral_id, contract_id, commission_rule_id, expected, approved, status)
commission_payments(commission_id, paid_at, amount, receipt_path)
attachments(attachable_type, attachable_id, path, original_name, mime, size, uploaded_by)   disco privado
```

Ajustes em relação à lista do briefing:

- **`appointments` foi unificada em `visits`.** Visita externa, atendimento agendado, retorno, follow-up e reunião são tipos da mesma atividade. Duas tabelas com os mesmos campos gerariam contagem dupla.
- **`visit_results` virou colunas de `visits`** (resultado, horário efetivo, próxima ação). Os motivos de não realização ficam no catálogo `visit_reasons`.
- **`referrals` é uma tabela própria**, separada de `leads`, para não misturar indicação com produtividade do executivo.
- **`contract_receipts`** registra o dinheiro recebido. O contrato assinado nunca é tratado como receita recebida.

Regras gerais: valores em DECIMAL, chaves estrangeiras com `restrict` em históricos, soft delete em cadastros, transação em mudança de etapa e geração de comissão, CPF/CNPJ criptografado com hash para checar duplicidade.

## Distâncias e rotas sem API paga

- Distância estimada = distância em linha reta (haversine) × fator de estrada (padrão 1,30, configurável).
- OSRM ou OpenRouteService podem ser ligados depois pelo `.env`, sem ser obrigatórios.
- A localização do executivo é a do último check-in, capturada pelo navegador com consentimento. Não há rastreamento em tempo real.
