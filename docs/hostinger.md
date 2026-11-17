# Implantação na Hostinger (plano Premium)

## O que o plano oferece e como o projeto se adapta

| Recurso | No plano Premium | Como o sistema lida |
|---|---|---|
| PHP | Versão escolhida no hPanel (Avançado > Configuração do PHP) | Exige **PHP 8.3+** (Laravel 13). O `scripts/deploy.sh` confere antes de começar |
| SSH, Git e Composer | Disponíveis | Deploy por `git pull` + `composer install` |
| MySQL | Bancos criados no hPanel, host `localhost` | Configurado no `.env` |
| Node.js | Não roda aplicações | `public/build` é compilado na máquina local e versionado |
| Redis, Supervisor, processos permanentes | Não disponíveis | Fila e cache no MySQL. A fila é processada pelo agendador a cada minuto |
| Cron | Disponível no hPanel | Um único cron chama `schedule:run` a cada minuto |
| Pasta pública | `public_html` | Aplicação fora dela; `public_html` vira link para `ricarteprev/public` |
| Links simbólicos | Permitidos via SSH | Usados só para `public_html`. Comprovantes ficam no disco privado e são servidos por rota autenticada |

Antes do primeiro deploy, rode no SSH e guarde a saída:

```bash
php -v && php -m && composer --version && mysql --version && git --version
```

Confirme `pdo_mysql`, `mbstring`, `intl`, `gd`, `bcmath`, `zip`, `fileinfo` e `openssl` na lista de módulos.

## Primeiro deploy

Antes, conclua as etapas 1 a 6 de [configuracao-hostinger.md](configuracao-hostinger.md) (subdomínio, banco, PHP, SSH, GitHub).

1. **SSH:** clone o repositório na pasta do domínio, **fora** do `public_html`:

   ```bash
   cd ~/domains/ricarteprev.com
   git clone git@github.com:ricarteprevmkt/ricarteprev-comercial.git ricarteprev
   cd ricarteprev
   cp .env.production.example .env
   nano .env            # preencha APP_URL, DB_* e MAIL_*; salve com Ctrl+O, Enter, Ctrl+X
   composer install --no-dev --optimize-autoloader --no-scripts
   php artisan package:discover
   php artisan key:generate
   bash scripts/check-server.sh
   ```

   O PHP da Hostinger bloqueia a função `proc_open`. Por isso o Composer roda com `--no-scripts` e a descoberta de pacotes é feita em seguida. Pelo mesmo motivo, as tarefas agendadas rodam dentro do próprio `schedule:run` e o backup gera o SQL pelo PHP.

2. **Pasta pública:** troque a pasta do subdomínio por um atalho para `public`:

   ```bash
   cd ~/domains/ricarteprev.com/public_html
   mv comercial comercial_antigo
   ln -s ../ricarteprev/public comercial
   ```

   Se o sistema usar o domínio principal (e não um subdomínio), o atalho substitui o próprio `public_html`:

   ```bash
   cd ~/domains/ricarteprev.com
   mv public_html public_html_antigo
   ln -s ricarteprev/public public_html
   ```

3. **Banco e administrador:**

   ```bash
   cd ~/domains/ricarteprev.com/ricarteprev
   bash scripts/deploy.sh
   php artisan app:create-admin
   ```

4. **hPanel > Avançado > Cron jobs:** adicione, a cada minuto:

   ```
   cd /home/u316363488/domains/ricarteprev.com/ricarteprev && php artisan schedule:run >> /dev/null 2>&1
   ```

5. **hPanel > Segurança > SSL:** ative o certificado e force HTTPS.

6. **E-mail:** crie uma caixa no hPanel e preencha `MAIL_*` no `.env`. Sem isso, os links de criação e redefinição de senha não chegam aos usuários. Enquanto não houver e-mail, defina a senha inicial no próprio cadastro.

## Deploys seguintes

Na máquina local:

```bash
npm run build
git add -A && git commit -m "..." && git push
```

No servidor:

```bash
cd ~/domains/ricarteprev.com/ricarteprev && bash scripts/deploy.sh
```

## O que o cron executa

O único cron (`schedule:run` a cada minuto) dispara:

| Tarefa | Quando |
|---|---|
| Fila (exportações, avisos) | A cada minuto |
| Avisos de visitas da próxima hora | A cada 15 minutos |
| Resumo diário de pendências no sino | Todo dia às 7h30 |
| Cópia de segurança do banco | Todo dia às 2h30 |
| Limpeza da auditoria (2 anos) e de jobs falhos | Mensal e semanal |
| Leitura do C2 Prev (resultado no INSS), se houver `C2_API_KEY` | A cada 5 minutos |

Para conferir no servidor: `php artisan schedule:list`.

## Backup

- **Cópia própria, diária:** `storage/app/private/backups/<banco>-AAAAMMDD-HHMMSS.sql.gz`, mantida por `BACKUP_KEEP_DAYS` dias (padrão 14). Usa o `mysqldump` quando existe; senão, gera o SQL pelo PHP. A senha do banco nunca aparece na linha de comando.
- **Cópia manual a qualquer momento:** `php artisan app:backup-database`.
- **Restaurar** (crie antes um banco vazio no hPanel e use os dados dele):

  ```bash
  gzip -dc storage/app/private/backups/ARQUIVO.sql.gz | mysql -u u316363488_comercial -p u316363488_comercial
  ```

- **Cópia fora do servidor:** uma vez por semana, baixe o arquivo mais recente pelo Gerenciador de Arquivos do hPanel (ou `scp`) e guarde em local seguro. Se o servidor for perdido, as cópias que estão nele vão junto.
- A Hostinger também mantém backups do plano (verifique a frequência no hPanel).

## Pontos ainda a confirmar no servidor real

- Versão exata do PHP e lista de extensões.
- Se o `mysqldump` está disponível pelo SSH (necessário para o backup próprio).
- Limite de upload (`upload_max_filesize`) para fotos de comprovantes.
