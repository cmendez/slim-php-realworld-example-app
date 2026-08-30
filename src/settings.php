<?php

declare(strict_types=1);

// Define root path
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__) . DS);
}

// Load .env file
if (file_exists(ROOT . '.env')) {
    $dotenv = new Dotenv\Dotenv(ROOT);
    $dotenv->load();
}


return [
    'settings' => [
        'displayErrorDetails'    => getenv('APP_ENV') !== 'production', // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // App Settings
        'app'                    => [
            'name' => getenv('APP_NAME'),
            'url'  => getenv('APP_URL'),
            'env'  => getenv('APP_ENV'),
        ],

        // Python API
        'python_api'             => [
            'url' => getenv('PYTHON_API_URL') ?: 'http://host.docker.internal:8080',
        ],

        // Renderer settings
        'renderer'               => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // Monolog settings
        'logger'                 => [
            'name'  => getenv('APP_NAME'),
            'path'  => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],

        // Database settings
        'database'               => [
            'driver'    => getenv('DB_CONNECTION'),
            'host'      => getenv('DB_HOST'),
            'database'  => getenv('DB_DATABASE'),
            'username'  => getenv('DB_USERNAME'),
            'password'  => getenv('DB_PASSWORD'),
            'port'      => getenv('DB_PORT'),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ],

        'cors' => null !== getenv('CORS_ALLOWED_ORIGINS') ? getenv('CORS_ALLOWED_ORIGINS') : '*',

        // jwt settings
        'jwt'  => [
            'secret' => getenv('JWT_SECRET'),
            'secure' => getenv('APP_ENV') === 'production',
            'header' => 'Authorization',
            'regexp' => '/Token\s+(.*)$/i',
            'passthrough' => ['OPTIONS']
        ],

        'unsplash' => [
            'access_key' => getenv('UNSPLASH')  // Don't forget to configure this variable in the .env file, example: UNSPLASH=xxxxxxxxxxx
        ]
    ],
];

