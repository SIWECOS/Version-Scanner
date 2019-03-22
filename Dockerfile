
FROM abiosoft/caddy:0.11.0-php-no-stats

LABEL MAINTAINER="David Jardin <david.jardin@cms-garden.org>"

RUN apk --update add bash php7-mcrypt php7-ctype php7-xml php7-simplexml php7-xmlwriter php7-fileinfo php7-sqlite3 php7-pdo_sqlite php7-simplexml supervisor redis && rm /var/cache/apk/*

COPY Docker/Caddyfile /etc/Caddyfile
COPY Docker/supervisord.conf /etc/supervisord.conf

COPY . /scanner
COPY .env.prod /scanner/.env

WORKDIR /scanner
RUN composer install \
    && chmod -R 777 /scanner/storage

EXPOSE 2015

ENTRYPOINT ["supervisord", "--nodaemon", "--configuration", "/etc/supervisord.conf"]