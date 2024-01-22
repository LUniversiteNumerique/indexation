FROM php:8.3-fpm

RUN apt update \
    && apt install -y zlib1g-dev g++ git libicu-dev zip libzip-dev zip \
    && docker-php-ext-install intl opcache pdo pdo_mysql \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip \
    && nginx \
    && supervisor

RUN mkdir -p /var/log/supervisor
COPY ./resources/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./resources/default.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www/site

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN curl -sS https://get.symfony.com/cli/installer | bash
RUN mv /root/.symfony/bin/symfony /usr/local/bin/symfony

COPY . /var/www/site/

CMD ["/usr/bin/supervisord"]
