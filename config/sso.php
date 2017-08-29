<?php

return [
    'base' => 'https://cert.vatsim.net/sso/',
    'sso_key' => env('SSO_KEY',''),
    'sso_secret' => env('SSO_SECRET', ''),
    'sso_method' => env('SSO_METHOD', ''),
    'sso_cert' => file_get_contents('.sso.rsa', '')
];
