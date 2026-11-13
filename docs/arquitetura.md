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

O C2 Prev continua sendo usado pela Ricarte. A integração decidida é a **importação de dados para o C2**. O formato (planilha exportada deste sistema ou API do C2) será detalhado na Fase 3, junto com o CRM.

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
