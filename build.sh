#!/bin/sh

cat /run/secrets/key > /www/.env
cat /run/secrets/api.env >> /www/.env
cat /run/secrets/sso.rsa >> /www/.sso.rsa

chown application:application /www/.env

if ($WWW_ENV == "prod") {
  crontab -l | { cat; echo "*    *    *     *     *    su -c 'cd /www && php artisan schedule:run' application"; } | crontab -
  crontab -l | { cat; echo "*    *    *     *     *    su -c 'cd /www && php artisan vatsim:update' application"; } | crontab -
  crond
}

/usr/bin/supervisord --nodaemon --configuration /etc/supervisord.conf
