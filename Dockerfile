FROM php:8.4-cli-alpine AS base

# pdo_pgsql é obrigatório: sem ele o Laravel falha com "could not find
# driver", mensagem que não indica a causa.
#
# postgresql-dev arrasta o toolchain de compilação inteiro (centenas de MB).
# Instalado como dependência virtual e removido após compilar a extensão,
# mantendo apenas libpq, que é o que o runtime precisa.
RUN apk add --no-cache --virtual .build-deps postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apk add --no-cache libpq \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

# --- desenvolvimento: código por bind mount, usuário com UID do host ---
FROM base AS dev

ARG UID=1000
ARG GID=1000
RUN addgroup -g "$GID" app 2>/dev/null || true \
    && adduser -u "$UID" -G app -D -h /home/app app 2>/dev/null || true \
    && mkdir -p /app/vendor \
    && chown -R "$UID:$GID" /app

ENV COMPOSER_HOME=/tmp/composer
USER app

# vendor/ é volume nomeado e nasce vazio: sem esta guarda, o artisan morre
# com "Failed opening required vendor/autoload.php" antes de haver chance de
# instalar as dependências.
CMD ["sh", "-c", "[ -f vendor/autoload.php ] || composer install --no-interaction; php artisan serve --host=0.0.0.0 --port=8000"]

# --- produção: código embutido, sem dependências de desenvolvimento ---
FROM base AS prod

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
