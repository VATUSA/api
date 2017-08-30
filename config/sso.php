<?php

return [
    'base' => 'https://cert.vatsim.net/sso/',
    'key' => env('SSO_KEY',''),
    'secret' => env('SSO_SECRET', ''),
    'method' => env('SSO_METHOD', ''),
    'cert' => (file_exists(base_path('.sso.rsa')) ? file_get_contents(base_path('.sso.rsa')) : ''),
    'return' => env('SSO_RETURN', 'https://login.vatusa.net/return'),
    'forumapi' => env('SMF_API','')
];
