# Configuração do servidor na Hostinger (plano Premium)

Passo a passo do que precisa ser feito no painel da Hostinger (hPanel) antes da primeira implantação. Faça na ordem. Os nomes dos menus podem mudar um pouco entre versões do hPanel; se não encontrar algum, use a busca do topo do painel.

Ao final de cada etapa há um **"Me envie"**: o que preciso receber para seguir. **Nunca me envie senhas**; elas vão direto para o arquivo `.env` no servidor.

---

## Etapa 1. Escolher o endereço do sistema

Sugestão: um subdomínio, como `comercial.ricarteprev.com.br`, para não misturar com o site institucional.

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
2. Aba **Versão do PHP:** selecione **8.3** (ou 8.4, se disponível) e salve.
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
   cd /home/USUARIO/domains/DOMINIO/ricarteprev && php artisan schedule:run >> /dev/null 2>&1
   ```
   Frequência: **a cada minuto** (`* * * * *`). Troque `USUARIO` e `DOMINIO` pelos seus dados.
2. **SSL:** painel do site → **Segurança** → **SSL** → instale para `comercial.ricarteprev.com.br` e ative **Forçar HTTPS**.
3. **E-mail:** painel → **Emails** → crie, por exemplo, `sistema@ricarteprev.com.br`. Os dados SMTP são `smtp.hostinger.com`, porta `465`, SSL. A senha vai no `.env` do servidor. Sem isso, os links de criação de senha não chegam aos usuários.

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
