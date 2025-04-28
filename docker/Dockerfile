
FROM php:8.4-fpm-alpine


RUN docker-php-ext-install pdo pdo_mysql

#Add zip extention
RUN apk add libzip-dev \
                    zip \
    && docker-php-ext-install zip \
    && docker-php-ext-install exif

RUN apk add autoconf \
    gcc g++ \
    libpng \
    npm \
    git \
    make \
    ffmpeg \
    && docker-php-ext-install pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && set -ex \
    && apk add libxml2-dev supervisor

RUN docker-php-ext-install soap

#RUN apk add   build-essential libssl-dev zlib1g-dev libpng-dev libjpeg-dev
RUN apk add freetype libpng libjpeg-turbo freetype-dev libpng-dev libjpeg-turbo-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j "$(nproc)" gd



RUN echo "max_file_uploads=100" >> /usr/local/etc/php/conf.d/docker-php-ext-max_file_uploads.ini
RUN echo "post_max_size=120M" >> /usr/local/etc/php/conf.d/docker-php-ext-post_max_size.ini
RUN echo "upload_max_filesize=120M" >> /usr/local/etc/php/conf.d/docker-php-ext-upload_max_filesize.ini


# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV COMPOSER_ALLOW_SUPERUSER 1

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Supervisor config
RUN mkdir -p /etc/supervisor.d/
COPY ./docker/supervisord/queue-worker.conf /etc/supervisor.d/supervisord.ini

# Make Supervisor the main process
CMD ["supervisord", "-c", "/etc/supervisor.d/supervisord.ini"]
