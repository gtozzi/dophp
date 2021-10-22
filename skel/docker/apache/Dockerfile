FROM php:7.4-apache
RUN apt-get update
RUN apt-get install -y libpq-dev locales locales-all
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql gettext
RUN a2enmod rewrite
RUN service apache2 restart
