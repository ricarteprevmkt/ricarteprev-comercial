# Ricarte Prev · Sistema de Gestão Comercial

Gestão de executivos comerciais, visitas externas, funil, custos e indicadores regionais da Ricarte Prev.

- Arquitetura e modelo de dados: [docs/arquitetura.md](docs/arquitetura.md)
- Fórmulas dos indicadores: [docs/metricas.md](docs/metricas.md)
- Implantação na Hostinger: [docs/hostinger.md](docs/hostinger.md)

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
| 3 | CRM e funil comercial | Próxima |
| 4 | Agenda e rotas | |
| 5 | Indicadores regionais e comissões | |
| 6 | Custos e resultados | |
| 7 | Painéis | |
| 8 | Relatórios, notificações, backup, implantação definitiva | |
