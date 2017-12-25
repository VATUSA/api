#!/bin/sh

echo /run/secrets/key > /www/.env
echo /run/secrets/api.env >> /www/.env
