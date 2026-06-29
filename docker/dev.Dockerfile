# Local development image — NOT used in production (prod uses the root Dockerfile,
# which runs nginx + php-fpm under supervisord). This one is a plain PHP CLI image
# that runs `php artisan serve` with dev dependencies installed.
FROM php:8.1-cli

# install-php-extensions handles all the build deps for us.
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/bin/

RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    bcmath \
    gmp \
    sodium \
    intl \
    zip \
    redis \
    @composer

WORKDIR /www

# Dependencies are installed at container start (see command in compose.dev.yml)
# so the mounted source + vendor stay in sync without rebuilding the image.
EXPOSE 8000
