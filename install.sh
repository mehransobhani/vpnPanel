#!/usr/bin/env bash
# نصب و راه‌اندازی خودکار پنل — پورت‌های آزاد را خودش پیدا می‌کند
# و با سرویس‌های دیگرِ همین سرور تداخل نمی‌کند.
#
#   bash install.sh              نصب یا به‌روزرسانی
#   bash install.sh --new-keys  کلیدهای REALITY را هم بازتولید کن
#                               (کانفیگ‌های قبلی بی‌اعتبار می‌شوند)
set -euo pipefail

new_keys=0
for arg in "$@"; do
    case "$arg" in
        --new-keys) new_keys=1 ;;
        -h|--help) sed -n '2,8p' "$0"; exit 0 ;;
        *) echo "گزینهٔ ناشناخته: $arg"; exit 1 ;;
    esac
done

cd "$(dirname "$0")"

c_ok=$'\e[32m'; c_bad=$'\e[31m'; c_warn=$'\e[33m'; c_dim=$'\e[2m'; c_b=$'\e[1m'; c_0=$'\e[0m'
ok()   { echo "  ${c_ok}✓${c_0} $*"; }
bad()  { echo "  ${c_bad}✗${c_0} $*"; }
warn() { echo "  ${c_warn}!${c_0} $*"; }
head_() { echo; echo "${c_b}$*${c_0}"; }
die()  { bad "$*"; exit 1; }

compose() { docker compose "$@"; }

# ── پورت اشغال است؟ ──────────────────────────────────────────────────────
# بدون لوله نوشته شده: با `set -o pipefail`، خروج زودهنگام `grep -q` باعث
# SIGPIPE روی تولیدکننده و کد خروجی ۱۴۱ می‌شود — یعنی «پیدا نشد» گزارش
# می‌کند حتی وقتی پورت واقعاً اشغال است.
port_busy() {
    local p="$1" out="" line field

    if command -v ss >/dev/null 2>&1; then
        out="$(ss -ltun 2>/dev/null || true)"
    elif command -v netstat >/dev/null 2>&1; then
        out="$(netstat -ltun 2>/dev/null || true)"
    fi

    if [ -n "$out" ]; then
        while IFS= read -r line; do
            for field in $line; do
                case "$field" in
                    *:"$p"|*."$p") return 0 ;;
                esac
            done
        done <<< "$out"
    fi

    # تست مستقل از ابزار سیستمی: اگر بشود وصل شد، یعنی کسی گوش می‌دهد.
    if (exec 3<>"/dev/tcp/127.0.0.1/$p") 2>/dev/null; then
        return 0
    fi

    return 1
}

# پورتی که همین حالا کانتینرهای خودمان منتشر کرده‌اند «تداخل» نیست
is_ours() {
    local ports
    ports="$(compose ps --format '{{.Ports}}' 2>/dev/null || true)"
    case "$ports" in
        *":$1->"*) return 0 ;;
    esac
    return 1
}

# اولین پورت آزاد از فهرست؛ اگر پورت فعلیِ خودمان اشغال باشد آن را مجاز می‌داند
pick_port() {
    local current="$1"; shift
    if [ -n "$current" ] && { ! port_busy "$current" || is_ours "$current"; }; then
        echo "$current"; return
    fi
    local p
    for p in "$@"; do
        if ! port_busy "$p"; then echo "$p"; return; fi
    done
    echo ""
}

env_get() { sed -n "s/^$1=//p" .env 2>/dev/null | tail -1; }

env_set() {
    local k="$1" v="$2"
    if grep -qE "^$k=" .env; then
        sed -i "s|^$k=.*|$k=$v|" .env
    else
        printf '%s=%s\n' "$k" "$v" >> .env
    fi
}

# ── ۱) پیش‌نیازها ────────────────────────────────────────────────────────
head_ "۱/۶ بررسی پیش‌نیازها"
command -v docker >/dev/null 2>&1 || die "داکر نصب نیست: curl -fsSL https://get.docker.com | sh"
docker compose version >/dev/null 2>&1 || die "افزونهٔ docker compose نصب نیست."
docker info >/dev/null 2>&1 || die "سرویس داکر اجرا نمی‌شود: systemctl start docker"
ok "داکر $(docker version --format '{{.Server.Version}}') آماده است"

[ -f .env ] || { cp .env.example .env; ok ".env از روی .env.example ساخته شد"; }

# ── ۲) انتخاب پورت‌ها ────────────────────────────────────────────────────
head_ "۲/۶ انتخاب پورت‌های آزاد"

proxy_on_host=0
if port_busy 80 || port_busy 443; then
    if ! is_ours 80 && ! is_ours 443; then proxy_on_host=1; fi
fi

app_port="$(pick_port "$(env_get APP_PORT)" 8080 8090 8100 8110 8120 9080)"
[ -n "$app_port" ] || die "پورت آزادی برای پنل پیدا نشد."

if [ "$proxy_on_host" = "1" ]; then
    app_bind="127.0.0.1"
    warn "سرویس دیگری روی ۸۰/۴۴۳ فعال است — پنل فقط روی لوکال‌هاست باز می‌شود."
    warn "برای دسترسی از اینترنت، آن را پشت همان ریورس‌پروکسی ببرید (پایین توضیح داده شده)."
else
    app_bind="0.0.0.0"
fi

xray_port="$(pick_port "$(env_get XRAY_PORT)" 443 8443 2053 2083 2087 2096)"
[ -n "$xray_port" ] || die "پورت آزادی برای Xray پیدا نشد."

env_set APP_PORT "$app_port"
env_set APP_BIND "$app_bind"
env_set XRAY_PORT "$xray_port"

ok "پنل: ${app_bind}:${app_port}"
ok "Xray: 0.0.0.0:${xray_port}"

# ── ۳) بالا آوردن سرویس‌ها ───────────────────────────────────────────────
head_ "۳/۶ ساخت و اجرای سرویس‌ها"

# تور ایمنی: اگر تشخیص پورت به هر دلیلی اشتباه کرده باشد، داکر خطای
# «port is already allocated» می‌دهد؛ پورت بعدی را امتحان می‌کنیم.
LOG=/tmp/vpn-install.log

bring_up() {
    local candidates=("$@") p out
    for p in "${candidates[@]}"; do
        env_set APP_PORT "$p"

        if out="$(compose up -d --build 2>&1)"; then
            printf '%s\n' "$out" >> "$LOG"
            app_port="$p"
            return 0
        fi

        printf '%s\n' "$out" >> "$LOG"

        # تطبیق با الگوی bash — نه با لوله، تا SIGPIPE نتیجه را خراب نکند.
        case "$out" in
            *"already allocated"*|*"address already in use"*|*"Address already in use"*)
                warn "پورت $p اشغال بود — پورت بعدی امتحان می‌شود."
                compose down --remove-orphans >/dev/null 2>&1 || true
                continue
                ;;
        esac

        echo
        bad "بالا آوردن سرویس‌ها ناموفق بود. خطای داکر:"
        echo "${c_dim}────────────────────────────────────────${c_0}"
        printf '%s\n' "$out" | tail -25
        echo "${c_dim}────────────────────────────────────────${c_0}"
        echo "  لاگ کامل: $LOG"
        return 1
    done

    bad "هیچ‌کدام از پورت‌های پیشنهادی آزاد نبود."
    return 1
}

: > "$LOG"
bring_up "$app_port" 8090 8100 8110 8120 9080 || exit 1
ok "پنل روی ${app_bind}:${app_port}"
printf "  در انتظار آماده شدن اپ"
for _ in $(seq 1 60); do
    if [ "$(docker inspect "$(compose ps -q app)" --format '{{.State.Health.Status}}' 2>/dev/null)" = "healthy" ]; then
        echo; ok "اپ آماده است"; break
    fi
    printf "."; sleep 3
done
echo

# ── ۴) دادهٔ اولیه ───────────────────────────────────────────────────────
head_ "۴/۶ دادهٔ اولیه"
# سیدر با firstOrCreate کار می‌کند، پس اجرای دوباره چیزی را خراب نمی‌کند.
compose exec -T app php artisan db:seed --force >/dev/null
ok "مدیر و پلن‌ها بررسی شدند"

# ── ۵) نود VPN ───────────────────────────────────────────────────────────
head_ "۵/۶ راه‌اندازی نود VPN"
setup_args=(--port="$xray_port")
if [ "$new_keys" = "1" ]; then
    setup_args+=(--force)
    warn "کلیدهای REALITY بازتولید می‌شوند — کانفیگ‌های قبلی بی‌اعتبار خواهند شد."
fi
if ! compose exec -T app php artisan panel:setup-local-node "${setup_args[@]}"; then
    die "راه‌اندازی نود ناموفق بود."
fi
compose --profile vpn up -d xray >/dev/null
sleep 4
compose exec -T app php artisan panel:sync-node >/dev/null 2>&1 || true

# ── ۶) بررسی نهایی ───────────────────────────────────────────────────────
head_ "۶/۶ بررسی سلامت"
compose exec -T app php artisan panel:doctor || true

# ── جمع‌بندی ─────────────────────────────────────────────────────────────
public_ip="$(curl -s --max-time 6 https://api.ipify.org || true)"

head_ "آماده است"
if [ "$app_bind" = "127.0.0.1" ]; then
    echo "  پنل:  http://127.0.0.1:${app_port}  ${c_dim}(فقط از داخل سرور)${c_0}"
    echo
    echo "  ${c_b}برای دسترسی از اینترنت${c_0}، به ریورس‌پروکسی موجودتان اضافه کنید:"
    echo
    echo "  ${c_dim}Caddy:${c_0}"
    echo "    panel.example.com {"
    echo "        reverse_proxy 127.0.0.1:${app_port}"
    echo "    }"
    echo
    echo "  ${c_dim}nginx:${c_0}"
    echo "    location / { proxy_pass http://127.0.0.1:${app_port}; proxy_set_header Host \$host; }"
    echo
    echo "  سپس در .env بگذارید:"
    echo "    APP_URL=https://panel.example.com"
    echo "    PANEL_SUB_DOMAIN=https://panel.example.com"
else
    echo "  پنل:  http://${public_ip:-IP_سرور}:${app_port}"
fi

echo
echo "  ${c_b}فایروال${c_0} — پورت Xray باید از اینترنت در دسترس باشد:"
echo "    ufw allow ${xray_port}/tcp"
[ "$app_bind" = "0.0.0.0" ] && echo "    ufw allow ${app_port}/tcp"

echo
echo "  ${c_b}ورود مدیر${c_0}:  admin@panel.local  /  password"
echo "  ${c_warn}اگر تازه نصب کرده‌اید، اولین کار رمز را عوض کنید.${c_0}"

echo
echo "  ${c_dim}عیب‌یابی هر زمان:  docker compose exec app php artisan panel:doctor${c_0}"
echo
