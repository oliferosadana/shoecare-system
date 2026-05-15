#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache storage/app/public storage/app/livewire-tmp

if [ ! -f storage/app/public/.htaccess ]; then
    cat > storage/app/public/.htaccess <<'HTACCESS'
Options -Indexes

<FilesMatch "\.(php|php[0-9]?|phtml|phar|cgi|pl|py|sh|bash)$">
    Require all denied
</FilesMatch>

<IfModule mod_php.c>
    php_flag engine off
</IfModule>
HTACCESS
fi

if [ ! -L public/storage ]; then
    php artisan storage:link >/dev/null 2>&1 || true
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${RUN_OPTIMIZE:-false}" = "true" ]; then
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
