FROM php:8.4.7-cli

RUN apt-get update && apt-get install -y \
    libonig-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
    zip unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        mbstring \
        zip \
        gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin \
    --filename=composer

# Non-root runtime user. .env lives on the host only (see docker-compose.yml
# env_file:) and is excluded by .dockerignore, so it's never in this build
# context — nothing secret to protect during composer install as root below,
# but the app itself must not run as root.
RUN useradd -u 10001 -m -d /home/app -s /bin/sh app

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN mkdir -p storage bootstrap/cache \
    && chown -R app:app /app \
    && chmod -R 775 storage bootstrap/cache

USER app

RUN php artisan storage:link || true

EXPOSE 8181

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8181"]
