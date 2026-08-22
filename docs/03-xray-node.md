# ۰۳ — آماده‌سازی سرور Xray (نود)

«نود» جایی است که ترافیک مشتری از آن رد می‌شود. دو حالت دارید:

| حالت | مناسب چه کسی | نصب |
|---|---|---|
| **الف) نود محلی** | همان سروری که پنل روی آن است، هم پنل باشد هم VPN | یک دستور |
| **ب) نود جدا** | پنل جای دیگر، VPN روی یک یا چند سرور خارجی | دستی روی هر سرور |

> ⚠️ **مهم:** نود باید جایی باشد که به اینترنت آزاد دسترسی دارد. اگر پنل را
> داخل ایران اجرا می‌کنید، حالت «الف» به درد نمی‌خورد و باید سراغ «ب» بروید.
> حالت «الف» وقتی درست است که خودِ پنل روی سرور خارج اجرا شود.

---

# الف) نود محلی — Xray روی همین سرور

پنل یک سرویس Xray در همان compose دارد. یک دستور همه‌چیز را آماده می‌کند:
کلید REALITY می‌سازد، `config.json` را می‌نویسد و سرور و اینباند را در پنل ثبت می‌کند.

```bash
docker compose exec app php artisan panel:setup-local-node \
    --address=IP_یا_دامنهٔ_عمومی_سرور \
    --port=443 \
    --sni=www.microsoft.com \
    --name="سرور اصلی" \
    --country=NL
```

سپس سرویس Xray را بالا بیاورید:

```bash
docker compose --profile vpn up -d xray
```

و تست کنید:

```bash
docker compose exec app php artisan panel:test-node "سرور اصلی"
```

خروجی موفق:

```
نسخهٔ Xray: 26.x.x
+---------------+----------+------+
| tag           | protocol | port |
+---------------+----------+------+
| vless-reality | vless    | 443  |
+---------------+----------+------+
```

**تمام.** از این به بعد هر سرویسی که فروخته شود خودکار روی همین نود ساخته می‌شود.
نه SSH لازم است، نه کلید، نه تنظیم دستی.

### نکته‌های حالت محلی

- **تعارض پورت ۴۴۳:** اگر Xray را روی ۴۴۳ بگذارید، پنل نمی‌تواند همان‌جا HTTPS بدهد.
  یا Xray را روی پورت دیگری ببرید (`--port=8443`) یا پنل را روی زیردامنه/پورت دیگر سرو کنید.
- **پورت را در فایروال باز کنید:** `ufw allow 443/tcp`
- **پورت API (`10085`) publish نمی‌شود** و فقط از داخل شبکهٔ داکر در دسترس است.
- **کلید خصوصی REALITY** در `docker/xray/config.json` است و در `.gitignore` قرار دارد.
  از آن بکاپ بگیرید؛ با گم شدنش همهٔ کانفیگ‌های فروخته‌شده بی‌اعتبار می‌شوند.
- **بازتولید کلیدها:** `panel:setup-local-node --force` — بعد از آن همهٔ کانفیگ‌های
  قدیمی از کار می‌افتند و باید لینک جدید به مشتری‌ها بدهید.
- **دامنهٔ پوششی** (`--sni`) باید از خودِ نود سریع در دسترس باشد؛ REALITY برای هر
  اتصال با آن هندشیک می‌کند. اگر کند باشد، مشتری وصل نمی‌شود.

### افزودن اینباند بیشتر به نود محلی

`docker/xray/config.json` را ویرایش کنید، اینباند جدید را اضافه کنید، سپس:

```bash
docker compose --profile vpn restart xray
```

و همان اینباند را با **همان `tag`** در `مدیریت ← سرورها ← نود محلی` ثبت کنید.
نمونه‌های VMess+WS و Trojan+gRPC در انتهای همین سند آمده‌اند.

---

# ب) نود جدا — سرور مستقل با SSH

بقیهٔ این سند مربوط به این حالت است: یک سرور مجازی خارجی که پنل از طریق SSH
مدیریتش می‌کند. نمونهٔ زیر **VLESS + REALITY + Vision** است.

## پیش‌نیاز

یک سرور مجازی لینوکسی (اوبونتو ۲۲/۲۴) با IP اختصاصی و دسترسی `root`.

---

## گام ۱ — نصب Xray

روی **نود** (نه سرور پنل):

```bash
bash -c "$(curl -L https://github.com/XTLS/Xray-install/raw/main/install-release.sh)" @ install
```

بررسی:

```bash
xray version
# Xray 25.x.x (Xray, Penetrates Everything.)
```

مسیر باینری معمولاً `/usr/local/bin/xray` و کانفیگ `/usr/local/etc/xray/config.json` است.

---

## گام ۲ — ساخت کلید REALITY و shortId

```bash
xray x25519
```

خروجی دو خط است:

```
PrivateKey: yBl4Xf9k...          ← روی سرور می‌ماند
Password:   8k2f_pQ0Xr9vN3Ls...  ← همان public key، در پنل وارد می‌شود
```

> در نسخه‌های قدیمی‌تر خط دوم `Public key:` نام دارد. هر دو یک چیز هستند.

حالا یک shortId بسازید:

```bash
openssl rand -hex 8
# a1b2c3d4e5f60718
```

**هر سه مقدار را یادداشت کنید.**

---

## گام ۳ — انتخاب دامنهٔ پوششی

REALITY خودش را شبیه یک سایت واقعی نشان می‌دهد. دامنهٔ پوششی باید:

- TLS 1.3 و HTTP/2 داشته باشد
- **خارج از کشور شما** باشد
- از نود شما با پینگ خوب در دسترس باشد

گزینه‌های امتحان‌شده: `www.microsoft.com` · `www.lovelive-anime.jp` · `dl.google.com` · `www.cloudflare.com`

تست:

```bash
curl -sI --tlsv1.3 https://www.microsoft.com | head -1
```

---

## گام ۴ — نوشتن `config.json`

```bash
nano /usr/local/etc/xray/config.json
```

محتوا (سه مقدار `<...>` را جایگزین کنید):

```json
{
  "log": { "loglevel": "warning" },

  "api": {
    "tag": "api",
    "services": ["HandlerService", "StatsService"]
  },
  "stats": {},
  "policy": {
    "levels": {
      "0": { "statsUserUplink": true, "statsUserDownlink": true }
    },
    "system": {
      "statsInboundUplink": true,
      "statsInboundDownlink": true
    }
  },

  "inbounds": [
    {
      "tag": "api",
      "listen": "127.0.0.1",
      "port": 10085,
      "protocol": "dokodemo-door",
      "settings": { "address": "127.0.0.1" }
    },
    {
      "tag": "vless-reality",
      "listen": "0.0.0.0",
      "port": 443,
      "protocol": "vless",
      "settings": {
        "clients": [],
        "decryption": "none"
      },
      "streamSettings": {
        "network": "tcp",
        "security": "reality",
        "realitySettings": {
          "show": false,
          "dest": "www.microsoft.com:443",
          "xver": 0,
          "serverNames": ["www.microsoft.com"],
          "privateKey": "<PRIVATE_KEY از گام ۲>",
          "shortIds": ["<SHORT_ID از گام ۲>"]
        }
      },
      "sniffing": {
        "enabled": true,
        "destOverride": ["http", "tls", "quic"]
      }
    }
  ],

  "outbounds": [
    { "protocol": "freedom", "tag": "direct" },
    { "protocol": "blackhole", "tag": "blocked" }
  ],

  "routing": {
    "rules": [
      { "type": "field", "inboundTag": ["api"], "outboundTag": "api" },
      { "type": "field", "protocol": ["bittorrent"], "outboundTag": "blocked" }
    ]
  }
}
```

### چهار نکتهٔ حیاتی

1. **`"clients": []` را خالی بگذارید.** پنل کاربرها را در زمان اجرا اضافه می‌کند.
2. **بلوک `api` + `stats` + `policy` اجباری است.** بدون آن‌ها پنل نه می‌تواند کاربر
   اضافه کند و نه مصرف بخواند.
3. **`inboundTag: ["api"]` در routing لازم است**، وگرنه درخواست‌های API به بیرون می‌روند.
4. **`tag` اینباند** (اینجا `vless-reality`) را یادداشت کنید — عیناً همین را در پنل وارد می‌کنید.

اعتبارسنجی و اجرا:

```bash
xray run -test -config /usr/local/etc/xray/config.json
systemctl restart xray
systemctl enable xray
systemctl status xray --no-pager
```

---

## گام ۵ — باز کردن فایروال

```bash
ufw allow 22/tcp
ufw allow 443/tcp
ufw --force enable
```

> پورت `10085` را **باز نکنید**. API فقط روی `127.0.0.1` گوش می‌دهد و پنل از داخل SSH به آن می‌رسد.

---

## گام ۶ — دسترسی SSH برای پنل (توصیه‌شده: کلید)

روی **سرور پنل**:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/panel_node -N ""
ssh-copy-id -i ~/.ssh/panel_node.pub root@IP_نود
cat ~/.ssh/panel_node          # این را کپی کنید
```

تست:

```bash
ssh -i ~/.ssh/panel_node root@IP_نود "xray version"
```

---

## گام ۷ — ثبت نود در پنل

`مدیریت ← سرورها ← + سرور جدید`

| فیلد | مقدار |
|---|---|
| نام نمایشی | `آلمان ۱` |
| آدرس اتصال کاربر | IP یا دامنهٔ نود |
| کد کشور | `DE` |
| روش مدیریت | **SSH** |
| هاست SSH | خالی (یعنی همان آدرس بالا) |
| پورت / کاربر | `22` / `root` |
| کلید خصوصی | محتوای `~/.ssh/panel_node` |
| مسیر باینری | `/usr/local/bin/xray` |
| آدرس API | `127.0.0.1:10085` |
| مسیر config | `/usr/local/etc/xray/config.json` |

**ذخیره** بزنید، بعد **تست اتصال**.

پیام موفق:

```
اتصال موفق — Xray v25.x.x. اینباندهای نود: vless-reality (vless:443)
```

اگر خطا دید، سراغ بخش عیب‌یابی پایین بروید.

---

## گام ۸ — ثبت اینباند در پنل

در همان صفحه، **+ افزودن اینباند جدید**:

| فیلد | مقدار |
|---|---|
| Tag | `vless-reality` ← **دقیقاً مثل config.json** |
| پروتکل | `vless` |
| پورت | `443` |
| Network | `tcp` |
| Security | `reality` |
| SNI | `www.microsoft.com` ← همان `serverNames` |
| Fingerprint | `chrome` |
| Flow | `xtls-rprx-vision` |
| Public key (pbk) | مقدار `Password` از گام ۲ |
| Short ID (sid) | مقدار گام ۲ |
| SpiderX (spx) | `/` |
| قالب نام | `{brand}-{country}-{protocol}` |
| فعال | ✅ |

**افزودن** بزنید.

---

## گام ۹ — تست واقعی

`مدیریت ← کاربران ← یک کاربر ← ساخت سرویس دستی`

سپس در صفحهٔ سرویس، لینک `vless://...` را در v2rayNG یا v2rayN وارد کنید و وصل شوید.

تأیید اینکه کاربر واقعاً روی نود نشسته — روی **نود**:

```bash
xray api inbounduser --server=127.0.0.1:10085 -tag=vless-reality
```

باید `email` سرویس شما در خروجی باشد.

پس از چند دقیقه استفاده، مصرف را بخوانید:

```bash
docker compose exec app php artisan panel:sync-usage
```

عدد مصرف باید در پنل ظاهر شود. **تمام — نود آماده است.**

---

## اینباندهای بیشتر (اختیاری)

می‌توانید روی همان نود چند اینباند داشته باشید تا مشتری گزینه داشته باشد.

### VMess روی WebSocket پشت Cloudflare

به `inbounds` اضافه کنید:

```json
{
  "tag": "vmess-ws",
  "listen": "127.0.0.1",
  "port": 8080,
  "protocol": "vmess",
  "settings": { "clients": [] },
  "streamSettings": {
    "network": "ws",
    "wsSettings": { "path": "/ray" }
  }
}
```

جلوی آن یک nginx با گواهی SSL بگذارید که `/ray` را به `127.0.0.1:8080` پروکسی کند،
و در پنل اینباندی با `network=ws`, `security=tls`, `path=/ray`, `host=دامنه` بسازید.

### Trojan روی gRPC

```json
{
  "tag": "trojan-grpc",
  "listen": "0.0.0.0",
  "port": 2053,
  "protocol": "trojan",
  "settings": { "clients": [] },
  "streamSettings": {
    "network": "grpc",
    "security": "tls",
    "grpcSettings": { "serviceName": "TunSvc" },
    "tlsSettings": {
      "certificates": [{
        "certificateFile": "/etc/ssl/panel/fullchain.pem",
        "keyFile": "/etc/ssl/panel/privkey.pem"
      }]
    }
  }
}
```

> بعد از **هر** تغییر در `config.json` نود:
> `systemctl restart xray` و سپس در پنل **همگام‌سازی مجدد** بزنید،
> چون ری‌استارت Xray همهٔ کاربرانِ در حافظه را پاک می‌کند.

---

## عیب‌یابی اتصال نود

| پیام | علت | راه‌حل |
|---|---|---|
| `ورود SSH ناموفق بود` | کاربر/رمز/کلید غلط یا `PermitRootLogin` بسته | `ssh root@IP` را دستی تست کنید |
| `Xray روی نود پیدا نشد` | مسیر باینری اشتباه | `which xray` روی نود |
| `connection refused` روی API | بلوک `api` در config نیست یا Xray بالا نیست | `systemctl status xray` و بررسی config |
| `xray api adu: ... not found` | `tag` پنل با `tag` نود یکی نیست | هر دو را مقایسه کنید |
| مصرف همیشه صفر | بلوک `policy`/`stats` در config نیست | گام ۴ را دوباره ببینید |
| کاربر بعد از ری‌استارت Xray قطع شد | طبیعی است | **همگام‌سازی مجدد** بزنید |

تست از خط فرمان پنل:

```bash
docker compose exec app php artisan panel:test-node "آلمان ۱"
```

نسخهٔ Xray، فهرست اینباندها و تعداد کاربران دارای آمار را نشان می‌دهد.

---

## اگر SSH نمی‌خواهید

روش مدیریت سرور را روی **دستی** بگذارید. پنل کانفیگ و لینک اشتراک می‌سازد،
ولی افزودن کاربر روی نود با خودتان است:

```bash
# افزودن
echo '{"inbounds":[{"tag":"vless-reality","protocol":"vless","settings":{"clients":[
  {"id":"UUID-از-پنل","email":"EMAIL-TAG-از-پنل","flow":"xtls-rprx-vision","level":0}
]}}]}' | xray api adu --server=127.0.0.1:10085

# حذف
xray api rmu --server=127.0.0.1:10085 -tag=vless-reality EMAIL-TAG-از-پنل
```

`UUID` و `email tag` را از `مدیریت ← سرویس‌ها ← جزئیات ← اطلاعات فنی` بردارید.

---

▶ ادامه: **[۰۴ — اولین فروش](04-first-sale.md)**
