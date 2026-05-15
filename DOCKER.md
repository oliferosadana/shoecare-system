# Deploy ZOLIX Shoe Care dengan Docker

Panduan ini menjalankan aplikasi dengan 4 service:

- `app`: Laravel PHP-FPM
- `nginx`: web server
- `postgres`: database PostgreSQL
- `queue`: Laravel queue worker

## Kapasitas server

Minimum untuk uji production kecil:

```text
1 vCPU
1 GB RAM
20 GB SSD
```

Rekomendasi production awal:

```text
2 vCPU
2 GB RAM
30-50 GB SSD
```

Gunakan 50 GB SSD jika banyak upload foto before/after dan bukti transfer.

## Persiapan server

Install Docker dan Compose plugin di Ubuntu:

```bash
sudo apt update
sudo apt install -y ca-certificates curl gnupg
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

## Setup env

Di folder project:

```bash
cp .env.docker.example .env
nano .env
```

Wajib ubah:

```env
APP_URL=https://domain-kamu.com
APP_KEY=
DB_PASSWORD=password-kuat
AUTOGOPAY_TOKEN=token-autogopay
SESSION_SECURE_COOKIE=true
```

Generate `APP_KEY`:

```bash
RUN_MIGRATIONS=false RUN_OPTIMIZE=false docker compose run --rm app php artisan key:generate --show
```

Salin hasilnya ke `.env`:

```env
APP_KEY=base64:...
```

## Build dan jalankan

```bash
docker compose build
docker compose up -d
```

Lihat log:

```bash
docker compose logs -f app
docker compose logs -f nginx
```

Cek production:

```bash
docker compose exec app php artisan app:production-check
```

Hasil yang diharapkan:

```text
Warnings: 0
Failures: 0
```

## Akses aplikasi

Default compose membuka port:

```text
http://server-ip:8080
```

Jika pakai reverse proxy/Caddy/Traefik/Nginx host, arahkan domain ke port `8080`.

Jika ingin langsung port 80, ubah `.env`:

```env
APP_PORT=80
```

## SSL HTTPS

Rekomendasi paling sederhana:

- Pakai Cloudflare Tunnel, atau
- Pakai Nginx/Caddy di host sebagai reverse proxy ke `127.0.0.1:8080`.

Callback AutoGopay:

```text
https://domain-kamu.com/webhooks/autogopay
```

## Perintah operasional

Migrasi manual:

```bash
docker compose exec app php artisan migrate --force
```

Clear/cache ulang:

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

Backup database:

```bash
docker compose exec postgres pg_dump -U zolix zolix > backup-zolix.sql
```

Backup upload foto:

```bash
docker run --rm -v shoecare-system_zolix_public_uploads:/data -v "$PWD":/backup alpine tar czf /backup/uploads-backup.tar.gz -C /data .
```

Update aplikasi:

```bash
git pull
docker compose build
docker compose up -d
docker compose exec app php artisan app:production-check
```
