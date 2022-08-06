FROM instrumentisto/geckodriver

COPY start-app /usr/local/bin
COPY www /var/www

RUN apt-get update \
 && apt-get install -y --no-install-suggests \
            libapache2-mod-php7.4 apache2 cron php-curl php-zip \
 && rm -rf /var/lib/apt/lists/* \
           /tmp/* \
 && chown -R www-data: /var/www \
 && usermod --shell /bin/bash www-data
 
ENTRYPOINT ["start-app"]
