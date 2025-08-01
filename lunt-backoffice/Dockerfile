FROM php:8.3-fpm

ENV APP_ENV=prod
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt update \
    && apt install -y zlib1g-dev g++ git libicu-dev zip libzip-dev zip gettext-base \
    && docker-php-ext-install intl opcache pdo pdo_mysql \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip

RUN apt install -y nginx &&  \
    apt install -y vim && \
    apt install -y supervisor && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    rm /etc/nginx/sites-enabled/default && \
    mkdir -p /var/log/supervisor && \
    sed -i 's/client_max_body_size 25M;/client_max_body_size 128M;/g' /etc/nginx/nginx.conf

COPY ./resources/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./resources/default.conf.template /etc/nginx/conf.d/templates/default.conf.template
COPY ./resources/nginx-start.sh /root/nginx-start.sh
COPY ./custom-php.ini /usr/local/etc/php/conf.d/

WORKDIR /var/www/site
COPY . /var/www/site

RUN if [ "$APP_ENV" = "dev" ]; then  ["composer","install","-v"];  \
    else composer install --no-dev --optimize-autoloader; fi

RUN chmod +x /root/nginx-start.sh && \
    mkdir -p /var/log/php-fpm && mkdir -p /var/log/ngninx && \
    php bin/console cache:clear && php bin/console cache:warmup &&  \
    php bin/console assets:install && chmod -R a+rw public/uploads \
    && php bin/console as:co

# Forcer nginx et php-fpm à tourner en tant que root
RUN sed -i 's/^user\s\+www-data;/user root;/' /etc/nginx/nginx.conf \
    && sed -i 's/^user = www-data/user = root/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^group = www-data/group = root/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^listen.owner = www-data/listen.owner = root/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^listen.group = www-data/listen.group = root/' /usr/local/etc/php-fpm.d/www.conf

CMD ["/usr/bin/supervisord"]
