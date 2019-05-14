FROM siwecos/dockered-laravel:7.2

LABEL maintainer="Sascha Brendel <mail@lednerb.eu>"

# Settings [Further information: https://github.com/SIWECOS/dockered-laravel#env-options]
ENV USE_SCHEDULER true

# Copy application
COPY . .
COPY .env.example .env

RUN sed -i '/memory_limit = 128M/c\memory_limit = 256M' /usr/local/etc/php/php.ini

# Install all PHP dependencies and change ownership of our applications
RUN composer install --optimize-autoloader --no-dev --no-interaction \
    && touch database/database.sqlite \
    && chown -R www-data:www-data .

EXPOSE 80
