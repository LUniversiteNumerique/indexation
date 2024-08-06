FROM php:8.3-fpm

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
    apt install -y supervisor

RUN rm /etc/nginx/sites-enabled/default
RUN mkdir -p /var/log/supervisor
COPY ./resources/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./resources/default.conf.template /etc/nginx/conf.d/templates/default.conf.template
COPY ./resources/nginx-start.sh /root/nginx-start.sh
COPY custom-php.ini /usr/local/etc/php/conf.d/

RUN chmod +x /root/nginx-start.sh

WORKDIR /var/www/site

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /var/www/site/

RUN chmod a+rw public/uploads

RUN sed -i 's/client_max_body_size 25M;/client_max_body_size 128M;/g' /etc/nginx/nginx.conf

RUN ["composer","install","--no-dev","--optimize-autoloader"]

RUN mkdir -p /var/log/php-fpm
RUN mkdir -p /var/log/ngninx


CMD ["/usr/bin/supervisord"]
