FROM php:8.2-cli-alpine3.23

RUN php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer

ARG UID=1000
ARG GID=1000
RUN apk add -U shadow

RUN usermod -u $UID www-data \
    && groupmod -g $GID www-data

USER www-data

WORKDIR /var/www/stream-hub/stream-hub-symfony-bundle
