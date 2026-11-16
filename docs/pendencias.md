# Pendências

Itens que dependem de informação ou ação fora do código. Atualizado a cada fase.

## Bloqueantes para alguma fase

| # | Pendência | Depende de | Bloqueia |
|---|---|---|---|
| 1 | **Exportação para o C2 Prev:** documentação da API do C2, autenticação e formato (layout) do arquivo ou payload de saída | Daniel / C2 | Módulo de exportação |
| 2 | **Servidor Hostinger:** primeira implantação feita em 16/11/2026 (https://comercial.ricarteprev.com, PHP 8.5, MariaDB 11.8, repositório `ricarteprevmkt/ricarteprev-comercial`). Cron e e-mail (`sistema@ricarteprev.com`) configurados e testados. Falta só confirmar o primeiro backup automático (17/11, 2h30) | Daniel | — |

## Exportação para o C2 Prev (pendência 1)

Decisão: o sistema comercial **exporta** dados para o C2 Prev. O C2 continua sendo usado pela Ricarte, e este sistema não importa nada dele.

Para construir a exportação, preciso de:

- [ ] Documentação da API do C2 (endpoints, autenticação, limites de requisição), **ou** o layout do arquivo de importação aceito pelo C2 (CSV/planilha), se não houver API.
- [ ] Lista de campos obrigatórios e opcionais, com formato de cada um (datas, telefones, CPF, valores).
- [ ] Quais registros vão para o C2: só leads convertidos em contrato, todos os leads, ou contratos.
- [ ] Como o C2 identifica um registro já enviado (para não duplicar em reenvios).
- [ ] Como mapear os nossos catálogos (origens, etapas, motivos) para os valores do C2, incluindo o campo "Indicado por".
- [ ] Frequência desejada: manual (botão de exportar) ou automática (agendada).

Enquanto isso, a Fase 3 já guarda os dados necessários (CPF/CNPJ, telefone normalizado, origem, indicador, datas de cada etapa), de modo que a exportação seja só uma camada nova, sem refazer o CRM.

## Validações com a Ricarte Prev

- [ ] O custo da equipe (salários) entra no custo por contrato? Hoje entra só se for lançado na categoria "Equipe comercial", que está como **fixa** e não entra na margem.
- [ ] "Em acompanhamento" entra na base de conversão do funil? Hoje, não.
- [ ] Regras de comissão dos parceiros (valor, percentual e evento gerador).
- [ ] Cancelamento de contrato: o status existe, mas o fluxo (quem cancela, o que acontece com a oportunidade e com a comissão) precisa ser definido.

## Outros

- [ ] Tiles do mapa: o padrão usa o servidor público do OpenStreetMap, adequado para uso interno moderado. Se o uso crescer, contratar um provedor de tiles e trocar `MAP_TILE_URL` no `.env`.
- [ ] Chave do OpenRouteService no `.env` do servidor (`ORS_API_KEY`), para a distância das rotas pelas estradas. Sem ela, vale a estimativa pelo fator de estrada. Ver a Etapa 9 de [configuracao-hostinger.md](configuracao-hostinger.md).
- [x] E-mail SMTP de produção: `sistema@ricarteprev.com`, testado em 16/11/2026. A senha no `.env` vai entre aspas simples.
- [ ] Proposta comercial publicada precisa ser atualizada: ainda cita app nativo e portal PWA.
