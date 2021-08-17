FROM siwecos/dockered-laravel:7.2

LABEL maintainer="Sascha Brendel <mail@lednerb.eu>"

# Settings [Further information: https://github.com/SIWECOS/dockered-laravel#env-options]
ENV USE_SCHEDULER true

RUN echo 'memory_limit = 1024M' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini;

# Copy application
COPY . .
COPY .env.example .env

# Add required base files
ADD https://siwecos.github.io/Version-Scanner/signatures/candidates.json ./storage/signatures/candidates.json
ADD https://siwecos.github.io/Version-Scanner/signatures/signatures.json ./storage/signatures/signatures.json


# Install all PHP dependencies and change ownership of our applications
RUN composer self-update 1.10.22 \
    && composer install --optimize-autoloader --no-dev --no-interaction \
    && touch database/database.sqlite \
    && chown -R www-data:www-data .

EXPOSE 80
