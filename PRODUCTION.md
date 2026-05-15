# ZOLIX Shoe Care Production Checklist

## Server Requirements

- PHP 8.3 or newer with extensions required by Laravel.
- Composer 2.
- Node.js and npm for building assets.
- PostgreSQL recommended for production.
- HTTPS enabled at web server or reverse proxy.
- Web server document root must point to `public/`.

## Environment

Copy `.env.example` to `.env`, then set:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain`
- `APP_KEY` generated with `php artisan key:generate`
- `DB_CONNECTION=pgsql`
- PostgreSQL credentials
- `FILESYSTEM_DISK=public`
- `SESSION_ENCRYPT=true`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_HTTP_ONLY=true`
- `SESSION_SAME_SITE=lax`

Never commit the real `.env`.

## Deploy Commands

Run from project root:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan app:production-check --strict
```

If `public/hot` exists after running a local Vite dev server, delete it before production traffic is routed to the app.

Alternatively, on Linux servers you can run:

```bash
bash scripts/deploy-production.sh
```

Review the script before using it the first time, especially if your hosting provider does not allow maintenance mode or queue workers.

## First Admin

Create the first admin interactively:

```bash
php artisan app:create-admin admin@your-domain.com
```

Seed local/demo data only in staging or local environments. Do not keep demo credentials in production.

## Backups

For SQLite/local MVP backup:

```bash
php artisan app:backup
```

To exclude `.env` from the generated backup:

```bash
php artisan app:backup --without-env
```

For PostgreSQL production, use a managed database backup or scheduled `pg_dump`, plus backup `storage/app/public`.

Minimum backup targets:

- PostgreSQL database.
- `storage/app/public/order-photos`.
- `storage/app/public/payment-proofs`.
- `.env` kept securely outside public web root.

## AutoGopay QRIS

Set the API token from AutoGopay:

```bash
AUTOGOPAY_BASE_URL=https://v1-gateway.autogopay.site
AUTOGOPAY_TOKEN=your_autogopay_api_token
AUTOGOPAY_TIMEOUT=20
```

Webhook URL:

```text
https://your-domain/webhooks/autogopay
```

AutoGopay does not provide a separate webhook secret. The app verifies `X-Signature` using `AUTOGOPAY_TOKEN`.

Public customer payment routes:

- `POST /track/{invoiceNumber}/qris` generates QRIS for the server-calculated remaining balance.
- `PATCH /track/{invoiceNumber}/qris/{payment}/check` lets customers refresh QRIS status.
- `POST /track/{invoiceNumber}/payment-method` records Cash or Transfer Manual requests.
- `POST /track/{invoiceNumber}/payment-method/{payment}/proof` uploads transfer proof.

These routes are rate-limited. QRIS generation does not accept customer-supplied nominal values.

## Security Checks

- Ensure `APP_DEBUG=false`.
- Ensure `/storage` only exposes intended uploaded files.
- Ensure `public/` is the only web root.
- Ensure uploaded files in `storage/app/public` are served as static files only and cannot execute scripts.
- Apache deployments should keep `storage/app/public/.htaccess`; Nginx deployments should also deny PHP/script execution in `/storage`.
- Ensure max upload size supports customer photos and transfer proof, but does not exceed operational needs.
- Set PHP upload limits at least `upload_max_filesize=8M` and `post_max_size=16M`.
- Use strong passwords for all users.
- Use role separation: `admin`, `kasir`, `staff`.
- Keep server, PHP, Composer dependencies, and npm dependencies updated.
- Use HTTPS only in production. The app sends HSTS only when the request is detected as secure.

## Nginx Storage Protection

If using Nginx, add a rule similar to this inside the server block to prevent script execution from uploaded files:

```nginx
location ~* ^/storage/.*\.(php|phtml|phar|cgi|pl|py|sh|asp|aspx)$ {
    deny all;
}
```

The main Laravel rule should still route requests through `public/index.php`.

## Queue Worker

For production with `QUEUE_CONNECTION=database`, run a queue worker with Supervisor/systemd:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

Supervisor example:

```ini
[program:zolix-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/shoecare-system/artisan queue:work --sleep=3 --tries=3 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/shoecare-system/storage/logs/queue-worker.log
stopwaitsecs=120
```

Restart workers after every deployment:

```bash
php artisan queue:restart
```

## Production Readiness Check

Run this on the server after setting `.env`, running migrations, linking storage, and building assets:

```bash
php artisan app:production-check --strict
```

The command checks:

- `APP_KEY`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, encrypted session, secure session cookies.
- Database connectivity and required tables.
- Storage link/path and write permission.
- Vite production manifest and absence of `public/hot`.
- AutoGopay base URL and token.
- Active admin user.
- Upload `.htaccess` protection for Apache.

## Operational Smoke Test

Before going live:

- Login as admin.
- Create an order with photo upload.
- Add pickup/delivery fee.
- Record partial payment, then full payment.
- Update status to process and ready pickup.
- Send WhatsApp links.
- Open invoice.
- Open public tracking page.
- Customer generates QRIS from tracking.
- Customer checks QRIS status from tracking.
- Customer chooses Transfer Manual and uploads proof.
- Admin verifies pending transfer from Payment Pending page.
- Check expired QRIS can be regenerated.
- Open `/payments/pending` and verify QRIS/transfer/cash filters.
- Check dashboard, schedule, reports, customers.
- Run `php artisan app:backup` and verify output.
