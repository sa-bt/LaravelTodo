FROM php:8.4-fpm-alpine

# پایه و پکیج‌های ضروری
RUN apk add --no-cache \
    libzip-dev zip \
    autoconf gcc g++ \
    libpng \
    npm git make ffmpeg \
    libxml2-dev supervisor \
    freetype libpng libjpeg-turbo freetype-dev libpng-dev libjpeg-turbo-dev \
    gmp gmp-dev  # اضافه کردن GMP

# PHP Extensions
RUN docker-php-ext-install pdo pdo_mysql zip exif pcntl soap bcmath gmp \
    && pecl install redis \
    && docker-php-ext-enable redis

# GD config
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j "$(nproc)" gd

# افزایش محدودیت آپلود
RUN echo "max_file_uploads=100" >> /usr/local/etc/php/conf.d/docker-php-ext-max_file_uploads.ini
RUN echo "post_max_size=120M" >> /usr/local/etc/php/conf.d/docker-php-ext-post_max_size.ini
RUN echo "upload_max_filesize=120M" >> /usr/local/etc/php/conf.d/docker-php-ext-upload_max_filesize.ini

# نصب Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER 1

# Supervisor config
RUN mkdir -p /etc/supervisor.d/
COPY ./docker/supervisord/queue-worker.conf /etc/supervisor.d/supervisord.ini

# Start
CMD ["supervisord", "-c", "/etc/supervisor.d/supervisord.ini"]
