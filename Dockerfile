ARG PHP_VERSION=8.3
FROM darrenedale/equit:php-${PHP_VERSION}-cli
RUN printf "bead\nbead\n" | adduser -u 1000 -h /bead-framework bead
