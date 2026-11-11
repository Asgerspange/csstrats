# -----------------------------------------
# Stage 1: Install Composer Dependencies
# -----------------------------------------
FROM serversideup/php:8.4.18-frankenphp-alpine3.22@sha256:934a5f0bcc1b83e795d72b3713cae6bcd3344697601ae1fef02c857d193ddb88 AS deps

WORKDIR /app

USER root
# Use apk instead of apt-get for Alpine
RUN apk add --no-cache git unzip
USER www-data
RUN git config --global url."https://github.com/".insteadOf git@github.com:

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN --mount=type=cache,target=/home/www-data/.composer/cache \
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=ext-bcmath

# -----------------------------------------
# Stage 2: Build Frontend (Node.js)
# -----------------------------------------
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package*.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci

COPY vite*.config.js tailwind.config.js* ./
COPY resources ./resources
COPY public ./public

RUN npm install
RUN npm run build

# -----------------------------------------
# Stage 3: Final Production Image
# -----------------------------------------
FROM serversideup/php:8.4.18-frankenphp-alpine3.22@sha256:934a5f0bcc1b83e795d72b3713cae6bcd3344697601ae1fef02c857d193ddb88

WORKDIR /var/www/csstrats

# Root-operationer samlet i ét lag; /tmp gøres skrivbar FØR bcmath kompileres,
# så compilerens test-binærer kan skrives til /tmp.
USER root
RUN apk add --no-cache gcc musl-dev; \
    echo 'int main(){return 0;}' > /tmp/t.c; \
    cc -o /tmp/t.out /tmp/t.c 2>&1 | head -20 || echo "CC_FAILED_RC=$?"; \
    ls -la /tmp/t.out 2>&1 || echo "NO_OUTPUT_FILE"; \
    ( /tmp/t.out; echo "RAN_RC=$?" ) || echo "RUN_FAILED"
# midlertidigt: ingen install-php-extensions her, kun compiler-test

# 1. Copy Dependencies
COPY --from=deps --chown=www-data:www-data /app/vendor ./vendor

# 2. Setup Storage Structure
USER root
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage
USER www-data

# 3. Copy Application Code
COPY --chown=www-data:www-data app ./app
COPY --chown=www-data:www-data bootstrap ./bootstrap
COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data database ./database
COPY --chown=www-data:www-data public ./public
COPY --chown=www-data:www-data resources ./resources
COPY --chown=www-data:www-data routes ./routes
COPY --chown=www-data:www-data artisan composer.json composer.lock ./

# 4. Clean up and bring in fresh assets
RUN rm -rf bootstrap/cache/*.php
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

# 5. Certificates
USER root
RUN mkdir -p storage/certs && chown -R www-data:www-data storage/certs
COPY --chown=www-data:www-data storage/certs/cacert.pem storage/certs/cacert.pem
COPY storage/certs/cacert.pem /etc/ssl/certs/cacert.pem
USER www-data

# 6. Set Production Mode
ENV APP_ENV=production
ENV OCTANE_SERVER=frankenphp

# 7. Force Octane to refresh its view of the code
RUN php artisan octane:install --server=frankenphp --no-interaction
RUN php artisan config:clear
RUN php artisan view:clear

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000", "--log-level=debug"]