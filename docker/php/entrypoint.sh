#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rw storage bootstrap/cache 2>/dev/null || true

if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] installing composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# کلید باید دقیقاً یک‌بار و پیش از کش کردن کانفیگ ساخته شود
if [ -f .env ] && ! grep -qE '^APP_KEY=base64:[A-Za-z0-9+/]{40,}=*$' .env; then
    echo "[entrypoint] generating application key..."
    php artisan config:clear >/dev/null 2>&1 || true
    php artisan key:generate --force
fi

# مقادیر .env به محیط کانتینر تزریق نمی‌شوند (تا متغیر خالی بر فایل اولویت نگیرد)،
# پس آنچه اینجا لازم داریم را مستقیم از فایل می‌خوانیم.
env_get() {
    sed -n "s/^$1=//p" .env | tail -1 | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"
}

if [ "$1" = "php-fpm" ]; then
    echo "[entrypoint] waiting for database..."

    db_host="$(env_get DB_HOST)"
    db_port="$(env_get DB_PORT)"
    db_user="$(env_get DB_USERNAME)"
    db_pass="$(env_get DB_PASSWORD)"

    connected=0
    for i in $(seq 1 60); do
        if php -r '
            try {
                new PDO(
                    sprintf("mysql:host=%s;port=%s", $argv[1], $argv[2] ?: 3306),
                    $argv[3],
                    $argv[4]
                );
                exit(0);
            } catch (Throwable $e) { exit(1); }
        ' "$db_host" "$db_port" "$db_user" "$db_pass" 2>/dev/null; then
            connected=1
            echo "[entrypoint] database ready after ${i}x2s"
            break
        fi
        sleep 2
    done

    if [ "$connected" != "1" ]; then
        echo "[entrypoint] WARNING: could not reach database at ${db_host}:${db_port:-3306} — continuing anyway"
    fi

    php artisan migrate --force --graceful
    php artisan storage:link 2>/dev/null || true
    php artisan config:clear
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
fi

exec "$@"
