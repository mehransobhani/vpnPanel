# ۰۶ — انتشار روی سرور واقعی

پنل را روی سروری بگذارید که مشتری‌ها به آن دسترسی دارند. لینک اشتراک روی همین
دامنه سرو می‌شود، پس باید پایدار و ترجیحاً پشت HTTPS باشد.

---

## گام ۱ — آماده‌سازی سرور

```bash
apt update && apt upgrade -y
curl -fsSL https://get.docker.com | sh
```

پروژه را منتقل کنید:

```bash
mkdir -p /opt/vpnpanel && cd /opt/vpnpanel
# با git clone یا rsync/scp
```

---

## گام ۲ — تنظیمات محیط

```bash
cp .env.example .env
nano .env
```

مقادیری که **حتماً** باید عوض شوند:

```dotenv
APP_ENV=production
APP_DEBUG=false                       # ⚠️ هرگز true نگذارید
APP_URL=https://panel.example.com
APP_PORT=8080                         # پشت nginx هاست می‌ماند

DB_PASSWORD=<یک رمز تصادفی بلند>
DB_ROOT_PASSWORD=<یک رمز تصادفی دیگر>

PANEL_SUB_DOMAIN=https://panel.example.com
PANEL_BRAND="نام برند"

LOG_LEVEL=warning
```

رمز تصادفی: `openssl rand -base64 32`

```bash
docker compose up -d --build
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan panel:admin you@example.com --password='<رمز قوی>'
```

سپس کاربر `admin@panel.local` را در پنل **مسدود یا حذف** کنید.

---

## گام ۳ — دامنه و HTTPS

روی هاست (بیرون داکر) nginx و certbot نصب کنید:

```bash
apt install -y nginx certbot python3-certbot-nginx
```

```bash
nano /etc/nginx/sites-available/panel
```

```nginx
server {
    listen 80;
    server_name panel.example.com;

    location / {
        proxy_pass         http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_read_timeout 120s;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/panel /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
certbot --nginx -d panel.example.com
```

certbot خودش HTTP را به HTTPS ریدایرکت می‌کند و تمدید گواهی را زمان‌بندی می‌کند.

### اعتماد به پروکسی

لاراول باید بداند پشت پروکسی است، وگرنه لینک‌های اشتراک با `http://` ساخته می‌شوند.
فایل `bootstrap/app.php` را ویرایش کنید:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
    ]);

    $middleware->redirectGuestsTo(fn () => route('login'));

    // 👇 این خط را اضافه کنید
    $middleware->trustProxies(at: '*');
})
```

```bash
docker compose restart app
docker compose exec app php artisan config:cache
```

بررسی:

```bash
curl -sI https://panel.example.com/login | head -1
```

---

## گام ۴ — فایروال

```bash
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
```

پورت نود VPN را هم باز کنید:

```bash
ufw allow 8443/tcp        # همان مقدار XRAY_PORT در .env
```

پورت `8080` نباید از بیرون در دسترس باشد. اگر خواستید کاملاً ببندید،
در `compose.yaml` بخش nginx را به این تغییر دهید:

```yaml
    ports:
      - "127.0.0.1:${APP_PORT:-8080}:80"
```

MySQL و Redis از ابتدا هیچ پورتی روی هاست باز نمی‌کنند.

---

## پنل و نود روی یک سرور

هر دو نمی‌توانند هم‌زمان پورت ۴۴۳ را بگیرند. سه راه:

| راه | پیکربندی |
|---|---|
| **ساده‌ترین** | Xray روی پورت دیگر: `panel:setup-local-node --port=8443` و پنل روی ۴۴۳ |
| **REALITY روی ۴۴۳** | Xray روی ۴۴۳ و پنل روی زیردامنه/پورت دیگر (مثلاً ۲۰۹۶) |
| **حرفه‌ای** | Xray روی ۴۴۳ با `fallbacks` به nginx، تا هر دو روی یک پورت باشند |

> REALITY روی پورت غیر ۴۴۳ کمی بیشتر جلب توجه می‌کند (خود Xray هم هشدار می‌دهد)،
> ولی در عمل معمولاً بدون مشکل کار می‌کند.

### بکاپ کلید نود

```bash
cp docker/xray/config.json /backups/xray-config-$(date +%F).json
```

این فایل کلید خصوصی REALITY را دارد. با گم شدنش، **همهٔ کانفیگ‌های فروخته‌شده
بی‌اعتبار می‌شوند** و باید به همهٔ مشتری‌ها لینک جدید بدهید.

---

## چک‌لیست امنیتی

- [ ] `APP_DEBUG=false` و `APP_ENV=production`
- [ ] رمزهای دیتابیس تصادفی و بلند
- [ ] کاربر `admin@panel.local` حذف یا رمزش عوض شده
- [ ] HTTPS فعال
- [ ] پورت ۸۰۸۰ فقط روی `127.0.0.1`
- [ ] بکاپ خودکار روزانه ([سند ۰۵](05-operations.md))
- [ ] `.env` در بکاپ جداگانه — بدون `APP_KEY` سشن‌ها و دادهٔ رمزنگاری‌شده از دست می‌روند
- [ ] `docker/xray/config.json` در بکاپ هست و وارد گیت نشده
- [ ] پورت Xray در فایروال باز است
- [ ] ورود با رمز روی نودها بسته شده (`PasswordAuthentication no`)

---

## کارایی

پیش‌فرض‌ها برای چند صد مشتری کافی است. اگر بار بالا رفت:

**worker بیشتر** — در `docker/php/supervisord.conf`:
```ini
numprocs=4
```

**opcache سخت‌گیرانه‌تر** — در `docker/php/php.ini`:
```ini
opcache.validate_timestamps = 0
```
> با این تنظیم بعد از هر تغییر کد باید `docker compose restart app` بزنید.

**php-fpm** — اگر همزمانی زیاد شد، در Dockerfile مقادیر `pm.max_children` را تنظیم کنید.

---

▶ ادامه: **[۰۷ — ساختار کد](07-architecture.md)**
