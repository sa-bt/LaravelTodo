FROM php:8.4-fpm-alpine

# ------------------------------------------------
# 1) پکیج‌های زمان‌اجرا (runtime) و ابزارهای لازم
# ------------------------------------------------
RUN set -eux; \
  apk add --no-cache \
    # runtime libs
    libzip libxml2 gmp freetype libpng libjpeg-turbo \
    supervisor ffmpeg git make \
    nodejs npm \
    # ابزارهای عمومی
    curl bash

# ------------------------------------------------
# 2) دیپندنسی‌های بیلد (فقط برای کامپایل اکستنشن‌ها)
# ------------------------------------------------
RUN set -eux; \
  apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    zlib-dev libzip-dev libxml2-dev gmp-dev \
    freetype-dev libpng-dev libjpeg-turbo-dev \
    linux-headers

# ------------------------------------------------
# 3) اکستنشن‌های هسته‌ای PHP
#    (zip قبلش configure می‌خواهد؛ gd هم نیاز به dev-libs دارد)
# ------------------------------------------------
RUN set -eux; \
  docker-php-ext-configure zip; \
  docker-php-ext-configure gd --with-freetype --with-jpeg; \
  docker-php-ext-install -j"$(nproc)" \
    pdo pdo_mysql zip exif pcntl soap bcmath gmp gd

# ------------------------------------------------
# 4) PECL Redis (کانال را آپدیت کن؛ در صورت نیاز نسخه را پین کن)
# ------------------------------------------------
RUN set -eux; \
  pecl channel-update pecl.php.net; \
  printf "\n" | pecl install -o -f redis \
    || printf "\n" | pecl install -o -f redis-6.0.2; \
  docker-php-ext-enable redis; \
  pecl clear-cache

# ------------------------------------------------
# 5) حذف دیپندنسی‌های بیلد و تمیزکاری
# ------------------------------------------------
RUN set -eux; \
  apk del .build-deps; \
  rm -rf /tmp/pear

# ------------------------------------------------
# 6) Composer (روش تمیز: فقط از ایمیج composer کپی کن)
# ------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# ------------------------------------------------
# 7) PHP upload limits
# ------------------------------------------------
RUN set -eux; \
  echo "max_file_uploads=100" > /usr/local/etc/php/conf.d/99-max_file_uploads.ini; \
  echo "post_max_size=120M"   > /usr/local/etc/php/conf.d/99-post_max_size.ini; \
  echo "upload_max_filesize=120M" > /usr/local/etc/php/conf.d/99-upload_max_filesize.ini

# ------------------------------------------------
# 8) Supervisor
# ------------------------------------------------
RUN mkdir -p /etc/supervisor.d/
COPY ./docker/supervisord/queue-worker.conf /etc/supervisor.d/supervisord.ini

CMD ["supervisord", "-c", "/etc/supervisor.d/supervisord.ini"]
