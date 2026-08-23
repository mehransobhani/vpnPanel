# ۰۱ — نصب و راه‌اندازی

## پیش‌نیازها

فقط دو چیز لازم دارید:

- **Docker** نسخهٔ ۲۴ به بالا
- **Docker Compose** نسخهٔ ۲ به بالا (دستور `docker compose`، نه `docker-compose`)

بررسی:

```bash
docker --version
docker compose version
```

PHP، Composer، MySQL و Node روی سیستم شما لازم **نیست** — همه داخل کانتینر هستند.

---

## گام ۱ — ساخت فایل تنظیمات

```bash
cd /مسیر/پروژه
cp .env.example .env
```

حالا `UID` و `GID` را برابر کاربر لینوکسی خودتان بگذارید تا فایل‌هایی که
کانتینر می‌سازد (لاگ، کش) متعلق به شما باشد و با خطای دسترسی روبه‌رو نشوید:

```bash
sed -i "s/^UID=.*/UID=$(id -u)/; s/^GID=.*/GID=$(id -g)/" .env
```

> روی macOS و Windows این کار لازم نیست ولی ضرری هم ندارد.

---

## گام ۲ — بالا آوردن پروژه

```bash
docker compose up -d --build
```

بار اول ۵ تا ۱۵ دقیقه طول می‌کشد (کامپایل افزونه‌های PHP). دفعات بعد چند ثانیه.

هنگام بالا آمدن، کانتینر `app` به‌صورت خودکار این کارها را می‌کند:

1. اگر `vendor/` نبود، `composer install` می‌زند
2. اگر `APP_KEY` خالی بود، **کلید برنامه را می‌سازد** (نیازی به اجرای دستی نیست)
3. منتظر آماده شدن MySQL می‌ماند
4. `php artisan migrate --force` را اجرا می‌کند
5. کش کانفیگ و روت را می‌سازد

> ⚠️ `php artisan key:generate` را **بعد از اولین اجرا دستی نزنید**. با عوض شدن
> کلید، سشن‌های فعال و دادهٔ رمزنگاری‌شده از کار می‌افتند.

وضعیت را ببینید:

```bash
docker compose ps
```

باید همهٔ سرویس‌ها `Up` باشند و `app` و `mysql` برچسب `(healthy)` بگیرند.

---

## گام ۳ — دادهٔ اولیه

```bash
docker compose exec app php artisan db:seed --force
```

این دستور یک مدیر و چهار پلن نمونه می‌سازد:

| ایمیل | رمز | نقش |
|---|---|---|
| `admin@panel.local` | `password` | مدیر |

حالا وارد <http://localhost:8080/login> شوید.

**اولین کار پس از ورود:** از مسیر `مدیریت ← کاربران ← admin` رمز عبور را عوض کنید.

---

## گام ۴ — نود VPN

پنل به‌تنهایی فقط کانفیگ می‌سازد؛ ترافیک باید از سرویس Xray رد شود.
Xray روی **همین سرور** اجرا می‌شود:

```bash
docker compose exec app php artisan panel:setup-local-node \
    --address=IP_عمومی_سرور --port=443
docker compose --profile vpn up -d xray
docker compose exec app php artisan panel:test-node
```

برای اینکه از این پس `docker compose up -d` خودش Xray را هم بالا بیاورد،
به `.env` اضافه کنید:

```dotenv
COMPOSE_PROFILES=vpn
```

> ⚠️ نود باید به اینترنت آزاد دسترسی داشته باشد؛ یعنی این پنل باید روی سروری
> **خارج از کشور** نصب شود. جزئیات: [۰۳ — نود VPN](03-xray-node.md).

---

## گام ۵ (اختیاری) — دادهٔ نمایشی

اگر می‌خواهید بدون داشتن سرور واقعی ببینید خروجی چه شکلی است:

```bash
docker compose exec app php artisan db:seed --class=DemoSeeder --force
```

یک مشتری (`customer@panel.local` / `password`) با یک سرویس فعال می‌سازد و
لینک‌های تولیدشده را در ترمینال چاپ می‌کند.

> اگر هنوز نود راه‌اندازی نشده باشد، هشدار می‌دهد و لینک خالی خواهد بود.

---

## تنظیمات مهم `.env`

پس از راه‌اندازی، این مقادیر را متناسب با کسب‌وکار خودتان تغییر دهید:

```dotenv
APP_NAME="نام برند شما"
APP_URL=https://panel.example.com     # دامنهٔ واقعی پنل
APP_PORT=8080                          # پورتی که روی هاست باز می‌شود

PANEL_BRAND="MyVPN"                    # نامی که روی کانفیگ‌ها می‌نشیند
PANEL_SUB_DOMAIN=https://panel.example.com   # دامنهٔ لینک اشتراک
PANEL_CURRENCY=IRT                     # IRT | IRR | USD
PANEL_EXPIRY_WARNING_DAYS=3

# اطلاعات کارت‌به‌کارت که در صفحهٔ پرداخت به مشتری نشان داده می‌شود
PANEL_CARD_NUMBER="6037-xxxx-xxxx-xxxx"
PANEL_CARD_HOLDER="نام صاحب حساب"
PANEL_BANK="ملی"

PANEL_SUPPORT_TELEGRAM="@yoursupport"
```

بعد از هر تغییر در `.env`:

```bash
docker compose restart app worker
docker compose exec app php artisan config:cache
```

---

## دستورات روزمرهٔ داکر

```bash
docker compose ps                   # وضعیت سرویس‌ها
docker compose logs -f app          # لاگ زندهٔ اپ
docker compose logs -f worker       # لاگ صف و زمان‌بند
docker compose restart app worker   # ری‌استارت
docker compose --profile vpn up -d  # بالا آوردن همراه نود VPN
docker compose down                 # خاموش کردن (داده‌ها می‌مانند)
docker compose down -v              # خاموش + پاک کردن دیتابیس ⚠️
docker compose exec app sh          # ورود به شل کانتینر
```

---

## مشکلات رایج

**پورت ۴۴۳ اشغال است**
اگر نود محلی روی ۴۴۳ است، پنل نمی‌تواند همان‌جا HTTPS بدهد. یکی را جابه‌جا کنید:
`panel:setup-local-node --port=8443` یا `APP_PORT` را عوض کنید.

**پورت ۸۰۸۰ اشغال است**
در `.env` مقدار `APP_PORT` را عوض کنید (مثلاً `8090`) و `docker compose up -d` بزنید.

**`app` مدام ری‌استارت می‌شود**
`docker compose logs app` را ببینید. معمولاً یعنی MySQL هنوز آماده نیست؛ چند ثانیه صبر کنید.

**خطای دسترسی روی `storage/`**
```bash
sudo chown -R $(id -u):$(id -g) storage bootstrap/cache
docker compose restart app
```

**خطای DNS هنگام build**
اگر `apk` نتوانست بسته‌ها را دانلود کند، DNS داکر مشکل دارد. در
`~/.docker/daemon.json` این را اضافه کنید و Docker را ری‌استارت کنید:
```json
{ "dns": ["1.1.1.1", "8.8.8.8"] }
```

**تغییرات کد اعمال نمی‌شود**
```bash
docker compose exec app php artisan optimize:clear
```

---

▶ ادامه: **[۰۲ — گشت‌وگذار در پنل](02-panel-tour.md)**
