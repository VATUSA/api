<?php

return [
    'driver' => env('MAIL_DRIVER'),
    'host' => env('MAIL_HOST', 'mail.vatusa.net'),
    'port' => env('MAIL_PORT', 587),
    'from' => ['address' => 'no-reply@vatusa.net', 'name' => 'VATUSA'],
    'encryption' => env('MAIL_ENCRYPT', null),
    'username' => env('MAIL_USERNAME', 'no-reply@vatusa.net'),
    'password' => env('MAIL_PASSWORD'),

    'sendmail' => '/usr/sbin/sendmail -bs',
    'pretend' => false,
];
