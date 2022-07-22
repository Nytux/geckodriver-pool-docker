FROM instrumentisto/geckodriver

COPY start-app /usr/local/bin
COPY www /var/www

RUN apt-get update \
 && apt-get install -y --no-install-suggests \
            libapache2-mod-php7.4 apache2 cron php-curl php-zip \
 && rm -rf /var/lib/apt/lists/* \
           /tmp/* \
 && echo 'export PATH="$PATH:/opt/firefox"' > /etc/profile.d/add_firefox_path.sh \
 && echo 'export PATH="$PATH:/opt/firefox"' >> /root/.bashrc \
 && chown -R www-data: /var/www/sessions \
 && echo "* * * * * root cd /var/www/cli/ && php -f autotest.php > /proc/1/fd/1" >> /etc/crontab
 
ENTRYPOINT ["start-app"]
