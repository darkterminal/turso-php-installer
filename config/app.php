<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you.
    |
    */

    'timezone' => date_default_timezone_get(),

    /*
    |--------------------------------------------------------------------------
    | Metadata File Path
    |--------------------------------------------------------------------------
    |
    | This value is the path to the metadata file used by the application.
    | It is determined based on the user's operating system and is stored
    | in the user's home directory, ensuring proper access and storage.
    |
    */

    'metadata' => get_plain_installation_dir() . DS . 'metadata.json',

    /*
    |--------------------------------------------------------------------------
    | SQL Statements Path
    |--------------------------------------------------------------------------
    |
    | This option defines the path where your SQL statement files are stored.
    | These files can be loaded and executed by the application as needed.
    | You should ensure this path is correct for your application's needs.
    |
    */

    'sql_statements' => app_path('database'),

    /*
    |--------------------------------------------------------------------------
    | Scripts Path
    |--------------------------------------------------------------------------
    |
    | This configuration option specifies the directory that contains your
    | application's script files. These scripts can be executed to perform
    | various tasks and should be organized within this directory.
    |
    */

    'scripts' => app_path('scripts'),

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => 'turso-php-installer',

    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | This value determines the "version" your application is currently running
    | in. You may want to follow the "Semantic Versioning" - Given a version
    | number MAJOR.MINOR.PATCH when an update happens: https://semver.org.
    |
    */

    'version' => app('git.version'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. This can be overridden using
    | the global command line "--env" option when calling commands.
    |
    */

    'env' => 'development',

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => [
        Turso\PHP\Installer\Providers\AppServiceProvider::class,
    ],

];
