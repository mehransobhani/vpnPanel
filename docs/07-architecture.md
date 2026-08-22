# ۰۷ — ساختار کد

برای وقتی که می‌خواهید پنل را شخصی‌سازی کنید.

---

## نقشهٔ فایل‌ها

```
app/
├── Models/
│   ├── Server.php          نود Xray؛ اطلاعات SSH رمزنگاری‌شده
│   ├── Inbound.php         یک اینباند روی یک سرور
│   ├── Plan.php            پلن فروش
│   ├── Subscription.php    سرویس مشتری — قلب سیستم
│   ├── Order.php           سفارش
│   ├── TrafficLog.php      مصرف روزانه
│   ├── User.php
│   ├── Setting.php         کلید-مقدار با کش دائمی
│   └── ActivityLog.php     رد پای عملیات
│
├── Services/
│   ├── SubscriptionService.php     ساخت / تمدید / ریست / چرخش کلید
│   └── Xray/
│       ├── LinkBuilder.php         ⭐ ساخت vless:// vmess:// trojan://
│       ├── SubscriptionFormatter.php  خروجی base64 / raw / clash
│       └── NodeClient.php          xray api — محلی یا از راه دور با SSH
│
├── Jobs/SyncSubscriptionToNode.php  افزودن/حذف کاربر روی یک نود
│
├── Console/Commands/
│   ├── SyncUsage.php          panel:sync-usage
│   ├── ExpireSubscriptions.php panel:expire
│   ├── SyncNode.php           panel:sync-node
│   ├── HealNodes.php          panel:heal-nodes
│   ├── SetupLocalNode.php     panel:setup-local-node
│   ├── TestNode.php           panel:test-node
│   └── MakeAdmin.php          panel:admin
│
├── Http/Controllers/
│   ├── SubscriptionLinkController.php  اندپوینت عمومی /sub/{token}
│   ├── {Dashboard,Plan,Order,Subscription}Controller.php
│   ├── Auth/{Login,Register}Controller.php
│   └── Admin/…
│
└── Support/
    ├── Format.php   بایت، پول، تاریخ شمسی
    └── QrCode.php   QR به صورت SVG

config/panel.php     تنظیمات اختصاصی پنل
routes/web.php       همهٔ مسیرها
routes/console.php   زمان‌بندی
```

---

## مدل داده

```
User ─┬─< Order >─── Plan >──< Server ──< Inbound
      └─< Subscription >──< Server        (plan_server)
              │            (server_subscription)
              └──< TrafficLog
```

### چهار شناسهٔ هر سرویس

| فیلد | نقش | جای استفاده |
|---|---|---|
| `uuid` | شناسهٔ کاربر | VLESS و VMess |
| `password` | رمز | Trojan |
| `token` | آدرس لینک اشتراک | `/sub/{token}` |
| `email_tag` | **کلید شمارش مصرف در Xray** | `statsquery` |

`email_tag` باید در کل نود یکتا باشد؛ سرویس آن را به شکل
`نام-کاربر-تصادفی@panel` می‌سازد.

---

## سه نقطهٔ کلیدی کد

### ۱. ساخت لینک — `LinkBuilder`

```php
$builder->build($inbound, $subscription);   // یک لینک
$builder->forSubscription($subscription);   // همهٔ لینک‌های سرویس
```

هر پروتکل متد خودش را دارد. پارامترها از دو متد مشترک می‌آیند:

- `transportParams()` — بسته به `network`: `path`، `host`، `serviceName`، `headerType`
- `securityParams()` — بسته به `security`: `sni`، `fp`، `alpn`، `pbk`، `sid`، `spx`

**افزودن یک پروتکل جدید:** یک متد private بنویسید و به `match` در `build()` اضافه کنید،
نامش را به `Inbound::PROTOCOLS` بیفزایید، و در `NodeClient::clientJson()` شکل client آن را تعریف کنید.

### ۲. خروجی اشتراک — `SubscriptionFormatter`

```php
$formatter->base64($subscription);  // v2rayNG / v2rayN
$formatter->raw($subscription);     // متن خام
$formatter->clash($subscription);   // YAML برای Clash Meta
```

فرمت از روی `?format=` یا User-Agent تشخیص داده می‌شود
(`SubscriptionLinkController::guessFormat()`).

**افزودن sing-box:** یک متد `singbox()` بنویسید که JSON برگرداند و در
کنترلر به `match` اضافه کنید.

### ۳. همگام‌سازی نود — `NodeClient`

```php
$client->ping($server);                    // نسخهٔ Xray
$client->discoverInbounds($server);        // خواندن config.json نود
$client->addUser($server, $subscription);  // xray api adu
$client->removeUser($server, $subscription); // xray api rmu
$client->fetchUsage($server);              // xray api statsquery -reset
```

بسته به `sync_driver` سرور، دستور را یا **محلی** در کانتینر `app` اجرا می‌کند
(نود داخل همین compose) یا از طریق **SSH** روی سرور راه دور. باینری Xray در ایمیج
`app` هست و فقط نقش کلاینت API را دارد. برای SSH از `phpseclib3` استفاده می‌شود و
اتصال هر سرور در همان درخواست کش می‌شود.

دو نکتهٔ ظریف که با آزمایش روی Xray واقعی به دست آمده‌اند:

- `xray api adu` بدون آرگومان صریح **`stdin:`** ورودی لوله‌شده را نادیده می‌گیرد و
  بی‌صدا `Added 0 user(s)` برمی‌گرداند. `runApi()` هم این آرگومان را می‌دهد و هم
  خروجی «۰ کاربر» را خطا حساب می‌کند.
- JSON ورودی `adu` حتماً باید **`port`** داشته باشد، چون Xray پیش از افزودن کاربر
  کل اینباند را می‌سازد و بدون پورت با «Listen on AnyIP but no Port(s) set» رد می‌کند.

هرگز مستقیم صدا نزنید — از `SyncSubscriptionToNode` استفاده کنید تا خطاها
در pivot ثبت شوند و تلاش مجدد انجام شود.

---

## جریان‌های اصلی

### خرید تا تحویل

```
OrderController::store()
   └─ Order (pending)

Admin\OrderController::approve()          ← داخل DB::transaction
   ├─ Order → paid
   └─ OrderController::fulfill()
        └─ SubscriptionService::create()
             ├─ ساخت Subscription (uuid, password, token, email_tag)
             ├─ تخصیص سرورهای پلن
             └─ dispatch SyncSubscriptionToNode برای هر سرور
                  └─ NodeClient::addUser()  →  xray api adu
```

### حساب‌داری ترافیک

```
scheduler (هر ۵ دقیقه)
   └─ panel:sync-usage
        ├─ NodeClient::fetchUsage()  →  statsquery -reset
        ├─ increment upload/download سرویس
        ├─ ثبت/جمع در traffic_logs
        └─ اگر از سقف رد شد → disable(EXHAUSTED) → حذف از نودها
```

---

## نکات مهم هنگام تغییر کد

**وضعیت سرویس همیشه از `isUsable()` بخوانید** — هم `status`، هم `expires_at` و
هم سقف ترافیک را با هم می‌سنجد.

**بعد از هر تغییری که قابل‌استفاده بودن را عوض می‌کند**، جابِ همگام‌سازی dispatch کنید،
وگرنه کاربر روی نود می‌ماند و از حجم رد می‌شود.

**رمز و کلید SSH با `encrypted` cast ذخیره می‌شوند** — با تغییر `APP_KEY` غیرقابل خواندن می‌شوند.

**`email_tag` را هرگز عوض نکنید** — تاریخچهٔ مصرف به آن گره خورده است.

**`Subscription::getRouteKeyName()` برابر `token` است** — پس مسیرها با توکن بایند می‌شوند، نه `id`.

---

## توسعه‌های پیشنهادی

| ایده | نقطهٔ شروع |
|---|---|
| درگاه پرداخت آنلاین | `OrderController::store()` — یک `payment_method` جدید |
| ربات تلگرام | فیلد `telegram_id` روی `User` از قبل هست |
| هشدار انقضا | `Schedule` در `routes/console.php` + Notification |
| API برای نمایندگان | `routes/api.php` + Sanctum |
| محدودیت واقعی IP | نیازمند خواندن `xray api inbounduser` و پارس لاگ |
| خروجی sing-box | `SubscriptionFormatter` |

### افزودن یک مهاجرت

```bash
docker compose exec app php artisan make:migration add_x_to_y_table
docker compose exec app php artisan migrate
```

### تست‌ها

```bash
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=LinkBuilderTest
```

تست‌ها روی SQLite در حافظه اجرا می‌شوند و به دیتابیس واقعی دست نمی‌زنند
(`phpunit.xml` عمداً کش کانفیگ production را دور می‌زند).

| فایل | چه چیزی را تضمین می‌کند |
|---|---|
| `LinkBuilderTest` | صحت پارامترهای هر سه پروتکل، حذف `flow` روی ترنسپورت غیر-TCP، کروشهٔ IPv6 |
| `SubscriptionEndpointTest` | خروجی base64/Clash، هدرهای مصرف، پاسخ خالی برای سرویس منقضی یا تمام‌حجم |
| `PurchaseFlowTest` | سفارش تا تحویل، کیف پول، تمدید بدون تغییر لینک، جلوگیری از تحویل دوباره، کنترل دسترسی |
| `HomePageTest` | نمایش پلن‌های فعال، ریدایرکت کاربر واردشده، محافظت از مسیرها |

هنگام افزودن قابلیت جدید، همان الگو را دنبال کنید: `Queue::fake()` در `setUp`
تا تست تلاشی برای اتصال SSH به نود نکند.

---

◀ بازگشت به **[README](../README.md)**
