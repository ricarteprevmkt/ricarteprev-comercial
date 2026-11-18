# Configuração do servidor na Hostinger (plano Premium)

Passo a passo do que precisa ser feito no painel da Hostinger (hPanel) antes da primeira implantação. Faça na ordem. Os nomes dos menus podem mudar um pouco entre versões do hPanel; se não encontrar algum, use a busca do topo do painel.

Ao final de cada etapa há um **"Me envie"**: o que preciso receber para seguir. **Nunca me envie senhas**; elas vão direto para o arquivo `.env` no servidor.

---

## Etapa 1. Escolher o endereço do sistema

Sugestão: um subdomínio, como `comercial.ricarteprev.com`, para não misturar com o site institucional.

1. hPanel → **Sites** → no site da Ricarte, clique em **Painel**.
2. Menu lateral → **Domínios** → **Subdomínios**.
3. Em "Criar um novo subdomínio", digite `comercial`.
4. Marque **Pasta personalizada** e digite `comercial` (vamos trocar essa pasta por um atalho na Etapa 7).
5. Clique em **Criar**.

**Me envie:** o endereço escolhido.

---

## Etapa 2. Criar o banco de dados MySQL

1. No painel do site → **Bancos de dados** → **Gerenciamento** (ou "Bancos de dados MySQL").
2. Em "Criar um novo banco de dados MySQL":
   - **Nome do banco:** `comercial` (a Hostinger adiciona um prefixo, ficando algo como `u123456789_comercial`).
   - **Usuário:** `comercial` (fica `u123456789_comercial`).
   - **Senha:** use o gerador do painel e guarde em um gerenciador de senhas.
3. Clique em **Criar**.
4. Na lista abaixo, confira o nome completo do banco e do usuário (com o prefixo).

O sistema e o banco ficam no mesmo servidor, então o host será `localhost`. Não é preciso liberar acesso remoto.

**Me envie:** nome completo do banco e do usuário (sem a senha).

---

## Etapa 3. Ajustar a versão e as extensões do PHP

1. No painel do site → **Avançado** → **Configuração do PHP**.
2. Aba **Versão do PHP:** selecione **8.5**, a mesma da linha de comando do servidor (conferida na Etapa 5), e salve. Versões a partir da 8.3 funcionam, mas o site e a linha de comando devem usar a mesma.
3. Aba **Extensões do PHP:** confirme que estas estão marcadas e salve:

   | Extensão | Para quê |
   |---|---|
   | `pdo_mysql` e `mysqli` | Conexão com o MySQL |
   | `mbstring` e `intl` | Textos em português e acentuação |
   | `bcmath` | Cálculos com valores em dinheiro |
   | `gd` (ou `imagick`) | Fotos de executivos e comprovantes |
   | `zip` | Exportação para Excel |
   | `fileinfo` | Conferir o tipo dos arquivos enviados |
   | `openssl` e `sodium` | Criptografia (CPF, sessões, senhas) |
   | `curl` | Serviços externos (e-mail, futura exportação para o C2) |
   | `exif` | Orientação correta das fotos tiradas no celular |

4. Aba **Opções do PHP:** ajuste e salve:

   | Opção | Valor |
   |---|---|
   | `memory_limit` | `512M` |
   | `upload_max_filesize` | `16M` |
   | `post_max_size` | `16M` |
   | `max_execution_time` | `120` |

**Me envie:** um print das extensões marcadas, se alguma da lista não existir.

---

## Etapa 4. Ativar o acesso SSH

1. No painel do site → **Avançado** → **Acesso SSH**.
2. Clique em **Ativar** (se aparecer o botão).
3. Anote os dados de conexão que aparecem na tela: **IP**, **porta** (normalmente `65002`) e **usuário** (algo como `u123456789`).
4. Defina a senha de SSH no próprio painel, **ou**, melhor, adicione uma chave SSH:
   - No seu computador, abra o **PowerShell** e rode:
     ```bash
     ssh-keygen -t ed25519 -C "ricarteprev-hostinger"
     ```
     Pressione Enter para aceitar o local padrão e defina uma frase de proteção.
   - Mostre a chave pública e copie o resultado inteiro:
     ```bash
     Get-Content $env:USERPROFILE\.ssh\id_ed25519.pub
     ```
   - No hPanel, em **Acesso SSH** → **Chaves SSH** → **Adicionar chave SSH**, cole e salve.

5. Teste a conexão pelo PowerShell (troque pelos seus dados):
   ```bash
   ssh -p 65002 u123456789@IP_DO_SERVIDOR
   ```
   Na primeira vez, digite `yes` para confirmar o servidor.

**Me envie:** "SSH funcionando" (não precisa enviar IP nem usuário).

---

## Etapa 5. Conferir o servidor pela linha de comando

Já conectado por SSH (Etapa 4), rode e me envie a saída completa:

```bash
php -v; echo "---"; php -m; echo "---"; composer --version; echo "---"; git --version; echo "---"; mysql --version; echo "---"; which mysqldump; echo "---"; ls ~/domains
```

Pontos de atenção:

- O `php -v` da linha de comando às vezes é diferente do PHP escolhido no painel. Se mostrar uma versão abaixo de 8.3, rode também `ls /opt/alt/ | grep php` e me envie: usaremos o caminho completo do PHP 8.3 no deploy.
- Se `composer --version` não funcionar, tente `composer2 --version`.

**Me envie:** a saída dos comandos.

---

## Etapa 6. Criar o repositório privado no GitHub

O servidor baixa o código do GitHub a cada atualização.

1. Em [github.com/new](https://github.com/new), crie o repositório `ricarteprev-comercial` como **Private**, sem README.
2. **Me avise** quando estiver criado. Eu configuro o remoto e envio o código. Faço o envio só depois da sua confirmação.
3. Para o servidor conseguir baixar o código, gere uma chave no servidor (conectado por SSH):
   ```bash
   ssh-keygen -t ed25519 -C "hostinger-deploy" -f ~/.ssh/github_deploy -N ""
   cat ~/.ssh/github_deploy.pub
   ```
4. Copie o resultado e, no GitHub, abra o repositório → **Settings** → **Deploy keys** → **Add deploy key**. Cole, dê o nome "Hostinger" e **não** marque "Allow write access".
5. Ainda no servidor, configure o uso dessa chave:
   ```bash
   printf 'Host github.com\n  IdentityFile ~/.ssh/github_deploy\n  IdentitiesOnly yes\n' >> ~/.ssh/config
   chmod 600 ~/.ssh/config
   ssh -T git@github.com
   ```
   A última linha deve responder com "successfully authenticated".

**Me envie:** "deploy key ok".

---

## Etapa 7. Primeira implantação

Com as etapas 1 a 6 feitas, sigo junto com você o roteiro de [hostinger.md](hostinger.md):

1. Clonar o repositório ao lado da pasta do subdomínio.
2. Criar o `.env` a partir do `.env.production.example`, com os dados do banco da Etapa 2 (a senha você digita direto no servidor).
3. Trocar a pasta `comercial` do subdomínio por um atalho para `public`.
4. Rodar `bash scripts/deploy.sh` e `php artisan app:create-admin`.
5. Rodar `bash scripts/check-server.sh` para conferir tudo.

---

## Etapa 8. Cron, SSL e e-mail

1. **Cron:** painel do site → **Avançado** → **Cron Jobs** → tipo **Personalizado** → comando:
   ```
   cd /home/u316363488/domains/ricarteprev.com/ricarteprev && /opt/alt/php85/usr/bin/php artisan schedule:run >> storage/logs/cron.log 2>&1
   ```
   Frequência: **a cada minuto** (`* * * * *`). (Caminho conferido na Etapa 5.)
   Use o caminho completo do PHP (`/opt/alt/php85/usr/bin/php`): o `php` do cron não é o mesmo do SSH, e com ele o cron não rodava. Para conferir se o cron está rodando: `tail storage/logs/cron.log`.
2. **SSL:** painel do site → **Segurança** → **SSL** → instale para `comercial.ricarteprev.com` e ative **Forçar HTTPS**.
3. **E-mail:** painel → **Emails** → crie, por exemplo, `sistema@ricarteprev.com`. Os dados SMTP são `smtp.hostinger.com`, porta `465`, SSL. A senha vai no `.env` do servidor. Sem isso, os links de criação de senha não chegam aos usuários.

## Etapa 9. Distância das rotas pelas estradas (OpenRouteService)

Sem esta etapa o sistema funciona, mas a distância das rotas é estimada (linha reta × fator de estrada). Com ela, a distância passa a ser a das estradas. É gratuito até 2.000 rotas por dia, bem acima do uso da equipe.

1. Acesse **openrouteservice.org** → **Sign up** e crie a conta com um e-mail da Ricarte Prev. Confirme o e-mail recebido.
2. Entre no painel (**Dashboard**) → aba **Tokens**.
3. Em **Token type**, deixe **Standard**; em **Token name**, escreva `ricarteprev-comercial`; clique em **CREATE TOKEN**.
4. Copie a chave que aparece (um texto longo).
5. No servidor, pelo SSH:
   ```bash
   cd ~/domains/ricarteprev.com/ricarteprev
   nano .env
   ```
   Na linha `ORS_API_KEY=`, cole a chave logo depois do `=`. Salve com **Ctrl+O**, **Enter**, **Ctrl+X**.
6. Ainda no SSH, aplique e recalcule as rotas dos últimos 30 dias:
   ```bash
   php artisan config:cache
   php artisan app:rebuild-routes
   ```
7. Confira em **Parâmetros → Rotas e deslocamento**: a mensagem deve ser "Distâncias calculadas pelas estradas".

A chave é um segredo: não envie para mim nem por WhatsApp; ela fica só no `.env` do servidor. Para o serviço só vão coordenadas (latitude e longitude), sem nome ou dado de cliente.

---

## Etapa 10. Integração com o C2 Prev

O sistema lê do C2 o resultado dos processos no INSS (deferido, indeferido, em andamento) e liga cada processo ao contato pelo CPF. Nada é enviado ao C2: a API dele é só de leitura. Sem esta etapa, o sistema funciona normalmente, sem a coluna "INSS" e sem o quadro "Resultado no INSS".

1. **Liberar a integração:** peça ao suporte do C2 Prev que libere a **API de Monitoramento** para o escritório da Ricarte Prev (é feito no painel administrativo deles).
2. **Gerar a chave:** com a integração liberada, um administrador da Ricarte entra no C2 → **Configurações → Integrações** → cria uma chave com as permissões de leitura de **processos**, **monitoramentos** e **eventos**. Copie a chave (só aparece uma vez).
   - O C2 também oferece webhook (aviso automático). Não é necessário: o sistema consulta o C2 a cada 5 minutos.
3. **Colocar a chave no servidor**, pelo SSH:
   ```bash
   cd ~/domains/ricarteprev.com/ricarteprev
   nano .env
   ```
   Na linha `C2_API_KEY=`, cole a chave logo depois do `=` (se a linha não existir, acrescente-a no final). Salve com **Ctrl+O**, **Enter**, **Ctrl+X**.
4. **Aplicar e testar:**
   ```bash
   php artisan config:cache
   php artisan app:c2-sync --check
   ```
   A resposta deve ser "Conexão com o C2 funcionando". Se aparecer "Faltam permissões", ajuste a chave no C2.
5. **Primeira leitura** (opcional, o agendador faz sozinho em até 5 minutos):
   ```bash
   php artisan app:c2-sync
   ```
   Com muitos processos, a carga inicial é dividida em várias rodadas para respeitar o limite da API; a mensagem "Carga inicial em andamento" é normal.
6. **Conferir** em **Administração → Integração C2 Prev**: última leitura, processos lidos e a lista de clientes do C2 sem contato no sistema (CPF ausente ou diferente no cadastro do contato).

A chave é um segredo: não envie para mim; ela fica só no `.env` do servidor. Do C2, o sistema guarda só o nome do cliente, o resultado e as datas do processo. O CPF não é gravado: só uma versão embaralhada (hash), usada para ligar ao contato.

O aviso de "contrato assinado sem cadastro no C2" usa o prazo de **Parâmetros → Integração com o C2 Prev** (2 dias) e só começa depois da carga inicial.

## Etapa 11. Totais do escritório no Dashboard (tarefa agendada do Claude)

Uma tarefa agendada no app do Claude, no seu computador, lê do C2 a cada 30 minutos só **totais** (financeiro, judicial, prazos, clientes e leads) e envia ao sistema. Ela precisa de uma chave combinada entre o servidor e o seu computador.

1. **Gerar a chave, no SSH:**
   ```
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```
   Copie o texto que aparecer.
2. **Colocar no servidor:** `nano .env` → no fim, `C2_SNAPSHOT_TOKEN='a-chave'` → salvar → `php artisan config:cache`.
3. **Colocar no seu computador:** crie a pasta `C:\Users\Daniel\.ricarteprev` e, dentro dela, o arquivo `c2-painel-token` (sem extensão) só com a chave, numa linha. No PowerShell:
   ```
   New-Item -ItemType Directory -Force "$env:USERPROFILE\.ricarteprev"; Set-Content -NoNewline -Encoding ascii "$env:USERPROFILE\.ricarteprev\c2-painel-token" "a-chave"
   ```
4. **Testar:** no app do Claude, em **Agendadas**, abra "Ricarte Prev: totais do C2 para o Dashboard" e clique em **Executar agora**. O resultado deve terminar em `HTTP 201`. No Dashboard aparece o bloco **Escritório (C2 Prev)**.

A tarefa só roda com o app do Claude aberto; se ele estiver fechado, roda quando abrir. O bloco mostra "Desatualizado" quando o último envio tem mais de 3 horas. A chave não deve ser enviada para mim.

---

## Resumo do que preciso receber

| Etapa | O que me enviar |
|---|---|
| 1 | Endereço do sistema |
| 2 | Nome completo do banco e do usuário (sem senha) |
| 3 | Extensões que não existirem, se houver |
| 4 | "SSH funcionando" |
| 5 | Saída dos comandos |
| 6 | "Repositório criado" e depois "deploy key ok" |
| 9 | "Rotas pelas estradas ok" (a chave fica só no servidor) |
| 10 | Saída do `php artisan app:c2-sync --check` (a chave fica só no servidor) |
| 11 | A última linha da execução manual da tarefa ("Painel C2 enviado: HTTP …") |
