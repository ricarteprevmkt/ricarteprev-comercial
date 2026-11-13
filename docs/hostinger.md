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

1. **hPanel > Bancos de dados MySQL:** crie o banco e o usuário. Anote nome, usuário e senha.
2. **hPanel > Avançado > Configuração do PHP:** selecione PHP 8.3 ou superior.
3. **SSH:** clone o repositório ao lado do `public_html` do domínio:

   ```bash
   cd ~/domains/SEU_DOMINIO
   git clone URL_DO_REPOSITORIO ricarteprev
   cd ricarteprev
   cp .env.production.example .env
   nano .env            # preencha APP_URL, DB_* e MAIL_*
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   ```

4. **Pasta pública:** troque o `public_html` por um link para `public`:

   ```bash
   cd ~/domains/SEU_DOMINIO
   mv public_html public_html_antigo
   ln -s ricarteprev/public public_html
   ```

5. **Banco e administrador:**

   ```bash
   cd ~/domains/SEU_DOMINIO/ricarteprev
   bash scripts/deploy.sh
   php artisan app:create-admin
   ```

6. **hPanel > Avançado > Cron jobs:** adicione, a cada minuto:

   ```
   cd /home/USUARIO/domains/SEU_DOMINIO/ricarteprev && php artisan schedule:run >> /dev/null 2>&1
   ```

7. **hPanel > Segurança > SSL:** ative o certificado e force HTTPS.

8. **E-mail:** crie uma caixa no hPanel e preencha `MAIL_*` no `.env`. Sem isso, os links de criação e redefinição de senha não chegam aos usuários. Enquanto não houver e-mail, defina a senha inicial no próprio cadastro.

## Deploys seguintes

Na máquina local:

```bash
npm run build
git add -A && git commit -m "..." && git push
```

No servidor:

```bash
cd ~/domains/SEU_DOMINIO/ricarteprev && bash scripts/deploy.sh
```

## Backup

- A Hostinger mantém backups do plano (verifique a frequência no hPanel).
- Um dump diário próprio do MySQL entra na Fase 8, agendado pelo mesmo cron.

## Pontos ainda a confirmar no servidor real

- Versão exata do PHP e lista de extensões.
- Se o `mysqldump` está disponível pelo SSH (necessário para o backup próprio).
- Limite de upload (`upload_max_filesize`) para fotos de comprovantes.
