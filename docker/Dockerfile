FROM php:8.3-cli-alpine

RUN apk add --no-cache oniguruma unzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS oniguruma-dev \
    && docker-php-ext-install -j$(nproc) pdo_mysql pcntl mbstring \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN addgroup -g 1000 app && adduser -D -u 1000 -G app app
WORKDIR /app
RUN chown app:app /app
USER app
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache
COPY --chown=app:app composer.json composer.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader
COPY --chown=app:app . .
# Runtime values come from Compose; the Laravel test runner expects this file to exist.
RUN touch .env \
    && composer dump-autoload --optimize --no-interaction
EXPOSE 8080
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080", "--no-reload"]
