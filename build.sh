#!/bin/sh

cat /run/secrets/key > /www/.env
cat /run/secrets/api.env >> /www/.env

chown application:application /www/.env

/usr/bin/supervisord --nodaemon --configuration /etc/supervisord.conf
