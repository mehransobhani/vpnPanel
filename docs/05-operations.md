# ۰۵ — مدیریت روزانه

---

## کارهای خودکار

کانتینر `worker` دو فرایند دارد: صف و زمان‌بند. این کارها خودکار انجام می‌شوند:

| کار | فاصله | توضیح |
|---|---|---|
| `panel:sync-usage` | هر ۵ دقیقه | مصرف را از نودها می‌خواند، اتمام‌حجم‌ها را قطع می‌کند |
| `panel:expire` | هر ساعت | سرویس‌های منقضی را غیرفعال و از نودها حذف می‌کند |
| `panel:heal-nodes` | هر ۱۰ دقیقه | اگر نودی ری‌استارت شده، کاربران را دوباره می‌نویسد |
| پاکسازی لاگ ترافیک | روزانه ۰۳:۳۰ | رکوردهای قدیمی‌تر از ۹۰ روز |
| `queue:prune-failed` | روزانه | jobهای شکست‌خوردهٔ قدیمی‌تر از ۷ روز |

بررسی سلامت:

```bash
docker compose logs -f worker
docker compose exec app php artisan schedule:list
```

---

## چطور شمارش ترافیک کار می‌کند

1. Xray مصرف هر کاربر را با کلید `email` نگه می‌دارد
2. پنل هر ۵ دقیقه `xray api statsquery -reset` می‌زند — یعنی مقدار را می‌خواند **و شمارنده را صفر می‌کند**
3. عدد خوانده‌شده به `upload`/`download` سرویس **اضافه** می‌شود
4. یک ردیف در `traffic_logs` برای گزارش روزانه ثبت می‌شود
5. اگر مصرف از سقف رد شد، سرویس `اتمام حجم` می‌شود و از همهٔ نودها حذف می‌شود

> چون از `-reset` استفاده می‌شود، **این دستور را دستی و مکرر اجرا نکنید** —
> هر بار شمارنده صفر می‌شود. زمان‌بند خودش کار را انجام می‌دهد.

---

## دستورات artisan

```bash
# آمار مصرف از همهٔ نودها (یا یک نود)
docker compose exec app php artisan panel:sync-usage
docker compose exec app php artisan panel:sync-usage --server=1

# منقضی کردن سرویس‌های تمام‌شده
docker compose exec app php artisan panel:expire

# بازنویسی همهٔ کاربران روی نود — بعد از ری‌استارت Xray
docker compose exec app php artisan panel:sync-node "آلمان ۱"
docker compose exec app php artisan panel:sync-node        # همهٔ نودها

# تست اتصال به نود
docker compose exec app php artisan panel:test-node "آلمان ۱"

# راه‌اندازی نود VPN روی همین سرور
docker compose exec app php artisan panel:setup-local-node --address=1.2.3.4 --port=443

# بررسی و ترمیم نودهایی که کاربرانشان پاک شده
docker compose exec app php artisan panel:heal-nodes

# ساخت یا ارتقای مدیر
docker compose exec app php artisan panel:admin you@example.com --name="مدیر" --password=StrongPass123
```

---

## صف

```bash
docker compose exec app php artisan queue:monitor default   # طول صف
docker compose exec app php artisan queue:failed            # کارهای شکست‌خورده
docker compose exec app php artisan queue:retry all         # تلاش دوباره
docker compose exec app php artisan queue:flush             # پاک کردن شکست‌خورده‌ها
docker compose restart worker                               # ری‌استارت worker
```

هر job همگام‌سازی تا ۱۵ دقیقه با وقفهٔ فزاینده تلاش می‌کند. اگر شکست بخورد،
پیام خطا در `سرویس‌ها ← جزئیات ← سرورهای این سرویس` روی همان نود ثبت می‌شود.

> کارهای مربوط به یک نود پشت قفل صف‌بندی می‌شوند تا چند اتصال SSH هم‌زمان به یک سرور باز نشود.

---

## بکاپ

### دیتابیس

```bash
docker compose exec -T mysql \
  sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction vpnpanel' \
  | gzip > backup-$(date +%F).sql.gz
```

### بازگردانی

```bash
gunzip < backup-2026-08-22.sql.gz | \
  docker compose exec -T mysql sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" vpnpanel'
```

### بکاپ خودکار روزانه

```bash
crontab -e
```

```cron
0 3 * * * cd /مسیر/پروژه && docker compose exec -T mysql sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction vpnpanel' | gzip > /backups/panel-$(date +\%F).sql.gz
0 4 * * * find /backups -name 'panel-*.sql.gz' -mtime +14 -delete
```

> فایل `.env` را هم جداگانه نگه دارید. بدون `APP_KEY` رمزهای SSH ذخیره‌شده
> **قابل بازیابی نیستند**.

> اگر نود محلی دارید، از `docker/xray/config.json` هم بکاپ بگیرید — کلید خصوصی
> REALITY آنجاست و با گم شدنش همهٔ کانفیگ‌های فروخته‌شده بی‌اعتبار می‌شوند.

---

## پایش

```bash
docker stats --no-stream                    # مصرف CPU/RAM کانتینرها
docker compose exec app php artisan about   # نسخه‌ها و درایورها
curl -s http://localhost:8080/up            # health check لاراول
docker compose exec app tail -f storage/logs/laravel.log
```

در داشبورد مدیریت هم این‌ها را می‌بینید: سرورهای قطع، سفارش‌های در انتظار،
سرویس‌های نزدیک انقضا، نمودار ترافیک ۱۴ روزه.

---

## عیب‌یابی

**مصرف به‌روز نمی‌شود**
```bash
docker compose exec app php artisan panel:sync-usage
```
اگر «هیچ سرور فعالی…» گفت: سرور غیرفعال است یا روی درایور «دستی».
اگر «پاسخ نامعتبر از statsquery» گفت: بلوک `policy`/`stats` در `config.json` نود نیست.

**کاربر روی نود اضافه نمی‌شود**
`panel:test-node` بزنید و `tag` پنل را با `tag` نود مقایسه کنید — باید عیناً یکی باشند.

**بعد از ری‌استارت Xray همه قطع شدند**
طبیعی است؛ کاربران فقط در حافظهٔ Xray هستند. زمان‌بند ظرف ۱۰ دقیقه خودش
ترمیم می‌کند، ولی می‌توانید فوراً هم بزنید:
```bash
docker compose exec app php artisan panel:heal-nodes
# یا بازنویسی کامل:
docker compose exec app php artisan panel:sync-node "نام سرور"
```

**نود محلی بالا نمی‌آید**
```bash
docker compose logs xray --tail 20
```
اگر `failed to load config` دید، `docker/xray/config.json` خراب است —
با `php artisan panel:setup-local-node` دوباره بسازیدش.

**لینک اشتراک خالی برمی‌گردد**
سرویس منقضی/اتمام‌حجم/غیرفعال است، یا سروری به آن تخصیص داده نشده.

**سایت ۵۰۰ می‌دهد**
```bash
docker compose exec app tail -50 storage/logs/laravel.log
docker compose exec app php artisan optimize:clear
```

**صفحه سفید بعد از تغییر کد**
```bash
docker compose exec app php artisan optimize:clear
docker compose restart app
```

---

## به‌روزرسانی پروژه

```bash
docker compose down
git pull                      # اگر روی git است
docker compose up -d --build
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
docker compose restart worker
```

---

▶ ادامه: **[۰۶ — انتشار روی سرور واقعی](06-production.md)**
