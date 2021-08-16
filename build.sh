#!/bin/sh

# cat /run/secrets/key > /www/.env
# cat /run/secrets/*.env >> /www/.env
# cat /run/secrets/sso.rsa >> /www/.sso.rsa
# chmod 600 /run/secrets/*.key
# chown application:application /run/secrets/*.key

# chown application:application /www/.env

mkdir /www/storage/logs
chown -R application:application /www/storage/logs

if [[ "$ENV" == "prod" ]] || [[ "$ENV" == "livedev" ]] || [[ "$ENV" == "staging" ]]; then
  # echo "*    *    *     *     *    cd /www && php artisan schedule:run" >> /etc/crontabs/application
  cd /www && php artisan migrate
  chmod -R 775 /www/storage/logs
  chmod -R 775 /www/storage/app/purifier/ # HTML Purifier
fi

/usr/bin/supervisord --nodaemon --configuration /etc/supervisord.conf
