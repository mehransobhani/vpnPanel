#!/bin/sh
# با set -e کار نمی‌کنیم: می‌خواهیم هر خطا با پیام روشن گزارش شود،
# نه اینکه کانتینر بی‌صدا بمیرد و فقط «unhealthy» ببینید.
set -u

cd /var/www/html

ME="$(id -u):$(id -g)"

fail() {
    echo ""
    echo "  ✗ [entrypoint] $1"
    echo ""
    exit 1
}

warn() {
    echo "  ! [entrypoint] $1"
}

step() {
    label="$1"
    shift
    if ! "$@"; then
        warn "«$label» با خطا مواجه شد — پنل ممکن است درست کار نکند."
        return 1
    fi
}

# ── آماده‌سازی دسترسی‌ها ──────────────────────────────────────────────────
# کانتینر به‌عنوان root اجرا می‌شود و php-fpm خودش worker‌ها را با www-data
# بالا می‌آورد. پس کافی است مسیرهای نوشتنی به www-data تعلق داشته باشند —
# مستقل از اینکه فایل‌های پروژه روی هاست مال چه کاربری هستند.
if [ "$(id -u)" = "0" ]; then
    mkdir -p storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache

    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
    [ -f .env ] && chown www-data:www-data .env 2>/dev/null || true

    # setgid: فایلی که بعداً با root ساخته شود گروهش را به ارث می‌برد
    # و برای www-data هم قابل نوشتن می‌ماند.
    find storage bootstrap/cache -type d -exec chmod 2775 {} + 2>/dev/null || true
fi

umask 0002

# ── پیش‌نیازها ────────────────────────────────────────────────────────────
[ -f .env ] || fail "فایل .env وجود ندارد.
    اجرا کنید:  cp .env.example .env"

if ! mkdir -p storage/framework/cache/data storage/framework/sessions \
              storage/framework/views storage/logs bootstrap/cache 2>/dev/null \
   || ! touch storage/logs/.write-test 2>/dev/null; then
    fail "کاربر کانتینر ($ME) اجازهٔ نوشتن در storage/ را ندارد.
    اگر کانتینر را با user دلخواه اجرا می‌کنید، آن را بردارید تا
    entrypoint خودش دسترسی‌ها را تنظیم کند."
fi
rm -f storage/logs/.write-test

chmod -R ug+rw storage bootstrap/cache 2>/dev/null || true

# ── وابستگی‌ها ────────────────────────────────────────────────────────────
if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] vendor/ پیدا نشد — نصب وابستگی‌ها (چند دقیقه طول می‌کشد)..."
    composer install --no-interaction --prefer-dist --optimize-autoloader \
        || fail "composer install ناموفق بود. اتصال اینترنت سرور را بررسی کنید."
fi

# ── کلید برنامه ───────────────────────────────────────────────────────────
# باید دقیقاً یک‌بار و پیش از کش کردن کانفیگ ساخته شود.
if ! grep -qE '^APP_KEY=base64:[A-Za-z0-9+/]{40,}=*$' .env; then
    [ -w .env ] || fail "APP_KEY خالی است ولی .env قابل نوشتن نیست (کاربر $ME).
    روی هاست:  sudo chown \$(id -u):\$(id -g) .env"

    echo "[entrypoint] ساخت کلید برنامه..."
    php artisan config:clear >/dev/null 2>&1
    php artisan key:generate --force || fail "ساخت APP_KEY ناموفق بود."
fi

# مقادیر .env به محیط کانتینر تزریق نمی‌شوند (تا متغیر خالی بر فایل اولویت نگیرد)،
# پس آنچه اینجا لازم داریم را مستقیم از فایل می‌خوانیم.
env_get() {
    sed -n "s/^$1=//p" .env | tail -1 | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"
}

if [ "${1:-}" = "php-fpm" ]; then
    db_host="$(env_get DB_HOST)"
    db_port="$(env_get DB_PORT)"
    db_user="$(env_get DB_USERNAME)"
    db_pass="$(env_get DB_PASSWORD)"

    echo "[entrypoint] انتظار برای دیتابیس ${db_host}:${db_port:-3306} ..."

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
            echo "[entrypoint] دیتابیس آماده شد (${i}x2s)"
            break
        fi
        sleep 2
    done

    if [ "$connected" = "1" ]; then
        step "migrate" php artisan migrate --force --graceful
    else
        warn "دیتابیس ${db_host}:${db_port:-3306} در دسترس نیست.
    مقادیر DB_* در .env را با سرویس mysql در compose.yaml مقایسه کنید.
    پنل بالا می‌آید ولی تا رفع مشکل کار نخواهد کرد."
    fi

    php artisan storage:link >/dev/null 2>&1 || true
    step "config:cache" sh -c 'php artisan config:clear >/dev/null && php artisan config:cache >/dev/null'
    step "route:cache" php artisan route:cache >/dev/null
    step "event:cache" php artisan event:cache >/dev/null

    echo "[entrypoint] آمادهٔ سرویس‌دهی."
fi

exec "$@"
