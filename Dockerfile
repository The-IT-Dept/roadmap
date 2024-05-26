FROM ghcr.io/iaa-inc/frankenphp:v1.0.2

# Copy app files
COPY artisan /app/artisan

COPY public /app/public
COPY public/build /app/public/build
COPY public/css /app/public/css
COPY public/fonts /app/public/fonts
COPY public/images /app/public/images
COPY public/js /app/public/js
COPY public/screenshots /app/public/screenshots

COPY lang /app/lang
COPY bootstrap/app.php /app/bootstrap/app.php
COPY routes /app/routes
COPY config /app/config
COPY database /app/database
COPY packages /app/packages
COPY resources/views /app/resources/views
COPY app /app/app

COPY composer.json composer.lock /app/
COPY vendor /app/vendor

# Create storage directories
RUN mkdir -p /app/bootstrap/cache
RUN mkdir -p /app/storage/app
RUN mkdir -p /app/storage/framework
RUN mkdir -p /app/storage/framework/cache
RUN mkdir -p /app/storage/framework/sessions
RUN mkdir -p /app/storage/framework/views
RUN mkdir -p /app/storage/logs

# Remove dev packages
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --no-progress --optimize-autoloader

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN echo "memory_limit=2048M" >> "$PHP_INI_DIR/php.ini"

ENTRYPOINT ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8080", "--admin-port=2019" ]
