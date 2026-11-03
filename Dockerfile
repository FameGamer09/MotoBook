FROM node:20-bookworm-slim AS assets

WORKDIR /src

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

ARG VITE_RIDER_ASSET_BASE=/A-rider/public/build/
ENV VITE_RIDER_ASSET_BASE=${VITE_RIDER_ASSET_BASE}

RUN npm run build \
    && npm ci --prefix A-rider \
    && npm run build --prefix A-rider

FROM php:8.2-cli-bookworm AS vendor

RUN apt-get update \
    && apt-get install -y --no-install-recommends libfreetype6-dev libjpeg62-turbo-dev libpng-dev libonig-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd mbstring pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction

FROM php:8.2-apache-bookworm AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libonig-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" curl gd mbstring pdo_mysql opcache \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /src/public/build ./public/build
COPY --from=assets /src/A-rider/public/build ./A-rider/public/build
COPY start-render.sh /usr/local/bin/start-render

RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/laravel-public.conf \
    && a2enconf laravel-public \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/framework/admin-sessions storage/logs bootstrap/cache admin/uploads/menu_items admin/uploads/banners admin/uploads/permits A-rider/public/uploads/pod A-rider/public/uploads/deposits \
    && chown -R www-data:www-data storage bootstrap/cache admin/uploads A-rider/public/uploads \
    && chmod +x /usr/local/bin/start-render

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
EXPOSE 10000

CMD ["/usr/local/bin/start-render"]
