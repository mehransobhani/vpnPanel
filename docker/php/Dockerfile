FROM php:8.4-fpm-alpine

# کتابخانه‌های زمان اجرا
RUN apk add --no-cache \
        bash git curl zip unzip supervisor su-exec \
        icu-libs oniguruma libzip libpng freetype libjpeg-turbo

# افزونه‌های PHP — ابزارهای کامپایل بعد از build حذف می‌شوند
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev oniguruma-dev libzip-dev \
        libpng-dev freetype-dev libjpeg-turbo-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring intl zip bcmath gd pcntl opcache sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && pecl clear-cache \
    && apk del --no-network .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# باینری Xray فقط به‌عنوان «کلاینت API» استفاده می‌شود:
# پنل با آن به نود محلی (سرویس xray) یا نودهای راه دور دستور می‌دهد
# و کلید REALITY تولید می‌کند. سرور Xray در کانتینر جداگانه اجرا می‌شود.
COPY --from=ghcr.io/xtls/xray-core:latest /usr/local/bin/xray /usr/local/bin/xray

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# php-fpm باید به‌عنوان کاربر هاست اجرا شود (compose آن را با user: تنظیم می‌کند)
RUN sed -i 's/^user = www-data/;user = www-data/; s/^group = www-data/;group = www-data/' \
        /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's|^listen = .*|listen = 9000|' /usr/local/etc/php-fpm.d/www.conf

ENV HOME=/tmp \
    XDG_CONFIG_HOME=/tmp \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
