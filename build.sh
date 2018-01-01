#!/bin/sh

cat /run/secrets/key > /www/.env
cat /run/secrets/*.env >> /www/.env
cat /run/secrets/sso.rsa >> /www/.sso.rsa

chown application:application /www/.env

mkdir /www/storage/logs
chown application:application /www/storage/logs

if [ "$WWW_ENV" == "prod" ]; then
  echo "*    *    *     *     *    cd /www && php artisan schedule:run" >> /etc/crontabs/application
  echo "*    *    *     *     *    cd /www && php artisan vatsim:flights" >> /etc/crontabs/application
fi

/usr/bin/supervisord --nodaemon --configuration /etc/supervisord.conf
