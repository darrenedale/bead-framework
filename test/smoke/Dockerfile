ARG PHP_VERSION=8.2

FROM nginx AS web

FROM darrenedale/equit:php-${PHP_VERSION}-fpm AS fpm
RUN apk add ssmtp
COPY ssmtp.conf /etc/ssmtp/
RUN chown root:www-data /etc/ssmtp/ssmtp.conf && \
  chmod 640 /etc/ssmtp/ssmtp.conf
USER www-data
