# Pendências

Itens que dependem de informação ou ação fora do código. Atualizado a cada fase.

## Bloqueantes para alguma fase

| # | Pendência | Depende de | Bloqueia |
|---|---|---|---|
| 1 | **Exportação para o C2 Prev:** documentação da API do C2, autenticação e formato (layout) do arquivo ou payload de saída | Daniel / C2 | Módulo de exportação |
| 2 | **Servidor Hostinger:** banco MySQL criado, SSH ativo, PHP 8.3+ com as extensões, saída do `scripts/check-server.sh` | Daniel (ver [configuracao-hostinger.md](configuracao-hostinger.md)) | Primeira implantação |
| 3 | **Repositório remoto:** repositório privado no GitHub para o deploy por `git pull` | Daniel | Primeira implantação |

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
- [ ] Regras de comissão dos indicadores (valor, percentual e evento gerador).
- [ ] Cancelamento de contrato: o status existe, mas o fluxo (quem cancela, o que acontece com a oportunidade e com a comissão) precisa ser definido.

## Outros

- [ ] E-mail SMTP de produção (sem ele, os links de criação de senha não chegam).
- [ ] Proposta comercial publicada precisa ser atualizada: ainda cita app nativo e portal PWA.
