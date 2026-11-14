# Ricarte Prev · Sistema de Gestão Comercial

Gestão de executivos comerciais, visitas externas, funil, custos e indicadores regionais da Ricarte Prev.

- Arquitetura e modelo de dados: [docs/arquitetura.md](docs/arquitetura.md)
- Fórmulas dos indicadores: [docs/metricas.md](docs/metricas.md)
- Configuração do servidor (passo a passo no hPanel): [docs/configuracao-hostinger.md](docs/configuracao-hostinger.md)
- Implantação na Hostinger: [docs/hostinger.md](docs/hostinger.md)
- Pendências: [docs/pendencias.md](docs/pendencias.md)

## Ambiente local (Laragon)

Requisitos: PHP 8.3+, Composer, MySQL 8 e Node 22 (só para compilar CSS/JS).

```bash
composer install
cp .env.example .env        # ajuste DB_DATABASE=ricarteprev
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=LocalDevSeeder   # usuários de teste, só em APP_ENV=local
npm install && npm run build
php artisan serve
```

Usuários de teste do `LocalDevSeeder`: `administrador@ricarteprev.test`, `gestor@…`, `executivo@…`, `indicador@…`, todos com a senha `password`. Eles nunca são criados em produção.

Crie o banco de testes `ricarteprev_testing` (utf8mb4) e rode:

```bash
composer test        # Pint + PHPStan + Pest
```

## Fases

| Fase | Conteúdo | Situação |
|---|---|---|
| 1 | Estrutura, autenticação, permissões, layout, migrations iniciais | Concluída |
| 2 | Cadastros: usuários, executivos, indicadores, regiões, cidades, metas, catálogos, parâmetros, auditoria | Concluída |
| 3 | CRM e funil comercial: contatos, oportunidades, histórico, propostas, contratos, recebimentos e painel do funil | Concluída |
| 4 | Agenda e rotas: visitas, registro em campo, reagendamento, mapa, rota estimada, despesas com comprovante, resumo do dia | Concluída |
| 5 | Indicadores regionais: indicações, atribuição, acompanhamento pelo funil, painel e mapa, comissões, área do indicador | Concluída |
| 6 | Custos e resultados: aprovação de despesas, custos por visita/contrato/região/executivo, receita contratada x recebida, margem, gráficos | Concluída |
| 7 | Painéis: Visão geral (equipe e próprio executivo), desempenho individual, metas, alertas | Concluída |
| 8 | Relatórios, notificações, backup, implantação definitiva | Próxima |
