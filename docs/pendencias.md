# Pendências

Itens que dependem de informação ou ação fora do código. Atualizado a cada fase.

## Bloqueantes para alguma fase

| # | Pendência | Depende de | Bloqueia |
|---|---|---|---|
| 1 | **Integração com o C2 Prev:** código pronto (leitura do resultado no INSS). Falta liberar a API no C2, gerar a chave e colocá-la no `.env` do servidor (Etapa 10 de [configuracao-hostinger.md](configuracao-hostinger.md)) | Daniel / C2 | Coluna "INSS", quadro "Resultado no INSS" e aviso de contrato sem cadastro no C2 |
| 2 | **Servidor Hostinger:** primeira implantação feita em 16/11/2026 (https://comercial.ricarteprev.com, PHP 8.5, MariaDB 11.8, repositório `ricarteprevmkt/ricarteprev-comercial`). Cron e e-mail (`sistema@ricarteprev.com`) configurados e testados. Falta só confirmar o primeiro backup automático (17/11, 2h30) | Daniel | — |

## Integração com o C2 Prev (pendência 1)

Decisão de 17/11/2026: **o sistema comercial é a fonte dos leads; o C2 é a fonte dos clientes.** A fronteira é o contrato assinado. A API do C2 é só de leitura, então o cadastro do cliente no C2 continua manual.

O que a integração faz:

- Lê do C2, a cada 5 minutos, os processos do Meu INSS e liga cada um ao contato pelo CPF (só o hash do CPF é guardado).
- Mostra o resultado (deferido, indeferido, em andamento) na lista de contratos e o quadro "Resultado no INSS" no painel do funil, com a taxa de deferimento por executivo, origem e parceiro. **Só indicador:** não altera contrato nem comissão.
- Avisa no sino quando um contrato assinado há mais de 2 dias (parâmetro ajustável) ainda não tem processo no C2.

A conferir quando houver dados reais:

- [ ] Os textos de situação e decisão que o C2 devolve. A classificação em deferido/indeferido é feita pelas palavras do texto (`InssOutcome::classify`); se o C2 usar outros termos, ajusto.
- [ ] Se um cliente com mais de um contrato precisa de resultado separado por contrato (hoje o resultado é por cliente).

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
