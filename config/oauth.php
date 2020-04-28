<?php
/*
 * VATSIM Connect
 */
return [

    /*
     * The location of the VATSIM OAuth interface
     */
    'base' => env('VATSIM_OAUTH_BASE', 'https://auth.vatsim.net'),

    /*
     * Redirect URL
     */
    'redirect' => env('VATSIM_OAUTH_REDIRECT', 'https://login.vatusa.net/return'),

    /*
     * The consumer key for your organisation (provided by VATSIM)
     */
    'id' => env('VATSIM_OAUTH_CLIENT'),

    /*
    * The secret key for your organisation (provided by VATSIM)
    * Do not give this to anyone else or display it to your users. It must be kept server-side
    */
    'secret' => env('VATSIM_OAUTH_SECRET'),

    /**
     * The scopes the user will be requested
     */
    'scopes' => explode(',', env('VATSIM_OAUTH_SCOPES')),

];
