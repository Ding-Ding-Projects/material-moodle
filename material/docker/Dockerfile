# Material Moodle: Moodle with the Material theme, passkey sign-in and the Material tools.
# Build from the repository root:  docker build -f material/docker/Dockerfile -t material-moodle .
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libicu-dev libxml2-dev \
      ghostscript unzip ca-certificates postgresql-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" pgsql pdo_pgsql zip gd intl soap exif opcache \
 && rm -rf /var/lib/apt/lists/*

COPY material/docker/php.ini /usr/local/etc/php/conf.d/moodle.ini
COPY material/docker/configure-php.php material/docker/php-settings.json /usr/local/share/material-runtime/
RUN php /usr/local/share/material-runtime/configure-php.php > /usr/local/etc/php/conf.d/zz-material-limits.ini
COPY material/docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers remoteip

WORKDIR /var/www/moodle
COPY --chown=www-data:www-data . /var/www/moodle
COPY material/docker/config.php /var/www/moodle/config.php
COPY material/docker/entrypoint.sh /usr/local/bin/material-entrypoint
COPY material/docker/healthcheck.sh /usr/local/bin/material-healthcheck
RUN chmod +x /usr/local/bin/material-entrypoint /usr/local/bin/material-healthcheck \
 && mkdir -p /var/www/moodledata && chown -R www-data:www-data /var/www/moodledata /var/www/moodle/config.php

VOLUME ["/var/www/moodledata"]
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=600s --retries=5 \
  CMD ["material-healthcheck"]
ENTRYPOINT ["material-entrypoint"]
CMD ["web"]
