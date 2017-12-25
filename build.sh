#!/bin/sh

cat /run/secrets/key > /www/.env
cat /run/secrets/api.env >> /www/.env
cat /run/secrets/sso.rsa >> /www/.sso.rsa

chown application:application /www/.env

mkdir /www/storage/logs
chown application:application /www/storage/logs

if [ "$WWW_ENV" == "prod" ]; then
  crontab -l | { cat; echo "*    *    *     *     *    su -c 'cd /www && php artisan schedule:run' application"; } | crontab -
  crontab -l | { cat; echo "*    *    *     *     *    su -c 'cd /www && php artisan vatsim:update' application"; } | crontab -
fi

/usr/bin/supervisord --nodaemon --configuration /etc/supervisord.conf
