INSTALAR VIA COMPOSER
https://ldaprecord.com/docs/core/v3/


DOCKERFILE PARA A INSTALACAO DO LDAP-PHP
FROM wpdiaries/wordpress-xdebug:6.4.2-php8.2-apache

RUN apt-get update && apt install -y libldap2-dev
RUN docker-php-ext-install ldap