#!/bin/sh

/usr/local/bin/php artisan migrate
/usr/local/bin/php artisan db:seed
