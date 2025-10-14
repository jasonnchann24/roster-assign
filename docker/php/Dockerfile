FROM php:8.3-fpm AS setup

# Starting from scratch
RUN apt-get clean && \ 
    apt-get -y autoremove && \ 
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Dependencies
RUN apt-get update

RUN apt-get install -y libfreetype6-dev \ 
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    zlib1g-dev \
    libicu-dev \
    g++ \
    mariadb-client \
    supervisor \
    cron \
    libzip-dev \
    zip

RUN docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ --with-webp=/usr/include/ \
    && docker-php-ext-configure intl

RUN docker-php-ext-install pdo_mysql \
    exif \
    pcntl \
    bcmath \
    intl \
    gd \
    opcache \
    zip

RUN pecl install redis \
    && docker-php-ext-enable redis

# Custom php.ini config
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# ---- Unique for supervisor only ---->

# Prepare log files
RUN touch /var/log/cron.log \
    && touch /var/log/queue.log \
    && touch /var/log/supervisord.log \
    && touch /var/log/websockets.log

# Add supervisor configuration
COPY ./docker/php/supervisord.conf /etc/supervisor/supervisord.conf
COPY ./docker/php/conf.d /etc/supervisor/conf.d

# <---- Unique for supervisor only ----

# Clean up
RUN apt-get clean \
    && apt-get -y autoremove \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Set up default directory
WORKDIR /var/www/api

RUN useradd -ms /bin/bash dockeruser
USER dockeruser