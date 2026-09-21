<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [
        // Used by the test suite (in-memory) and handy for quick local pokes.
        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix'   => '',
        ],
        'mysql'  => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', 'localhost'),
            'port'      => env('DB_PORT', '3306'),
            //'host'      => [env('DB_HOST2', 'localhost'), env('DB_HOST1','')],
            'database'  => env('DB_DATABASE', 'forge'),
            'username'  => env('DB_USERNAME', 'forge'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            // TLS is opt-in: set DB_SSL_CA to a CA bundle path and PDO encrypts the
            // connection and verifies the server certificate against it. Azure Database
            // for MySQL requires this (require_secure_transport=ON) and presents a chain
            // to DigiCert Global Root G2, already present in the image's
            // /etc/ssl/certs/ca-certificates.crt, with the server hostname in the SANs.
            //
            // Unset leaves `options` empty, which is exactly today's behaviour — the
            // DigitalOcean deployments, production included, must keep connecting over
            // the private VPC endpoint without TLS.
            'options'   => array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('DB_SSL_CA'),
            ]),
        ],
        'email'  => [
            'driver'    => env('DB_EMAIL_CONNECTION', 'mysql'),
            'host'      => env('DB_EMAIL_HOST', '127.0.0.1'),
            'port'      => env('DB_EMAIL_PORT', 3306),
            'database'  => env('DB_EMAIL_DATABASE', 'forum'),
            'username'  => env('DB_EMAIL_USERNAME', 'forum'),
            'password'  => env('DB_EMAIL_PASSWORD', ''),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            // Inherits DB_SSL_CA: the email schema lives on the same server as the main
            // one and moves with it. DB_EMAIL_SSL_CA overrides if that stops being true.
            'options'   => array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('DB_EMAIL_SSL_CA', env('DB_SSL_CA')),
            ]),
        ],
        'moodle' => [
            'driver'   => env('DB_MOODLE_CONNECTION', 'mysql'),
            'host'     => env('DB_MOODLE_HOST', '127.0.0.1'),
            'port'     => env('DB_MOODLE_PORT', 3306),
            'database' => env('DB_MOODLE_DATABASE', 'forum'),
            'username' => env('DB_MOODLE_USERNAME', 'forum'),
            'password' => env('DB_MOODLE_PASSWORD', ''),
            'prefix'   => 'mdl_',
            'strict'   => false,
            // Deliberately does NOT inherit DB_SSL_CA. The moodle schema stays on the
            // DigitalOcean managed cluster after the other schemas move to Azure, and
            // DigitalOcean presents a certificate from its own CA that is not in the
            // system roots — inheriting would break this connection the moment Azure TLS
            // is switched on. Set DB_MOODLE_SSL_CA explicitly if it ever needs TLS.
            'options'  => array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('DB_MOODLE_SSL_CA'),
            ]),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer set of commands than a typical key-value systems
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [
        'client' => 'predis',

        'default' => [
            'scheme' => env('REDIS_SCHEME', 'tls'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port'     => env('REDIS_PORT', 6379),
            'database' => 0,
        ],

    ],

];
