<?php

require_once './vendor/autoload.php';
$settings = require './src/settings.php';
$app = new \Slim\App($settings);
$container = $app->getContainer();
$container->register(new \Conduit\Services\Database\EloquentServiceProvider());
$config = $container['settings']['database'];

return [
    'paths' => [
        'migrations' => 'database/migrations',
        'seeds'      => 'database/seeds',
    ],
    'migration_base_class' => 'BaseMigration',
    'templates' => [
        'class' => 'TemplateGenerator',
    ],
    'aliases' => [
        'create' => 'CreateTableTemplateGenerator',
    ],

    'environments' => [
        'default_migration_table' => 'migrations',
        // OJO: En Render queremos que use 'production' por defecto si no se especifica
        // Cambiamos esto temporalmente o aseguramos pasar -e production
        'default_environment' => getenv('APP_ENV') === 'production' ? 'production' : 'development',
        
        'development' => [
            'name'       => $config['database'],
            'connection' => $container->get('db')->getConnection()->getPdo(),
        ],
        'production' => [
            'adapter'   => 'mysql', // Si $config['driver'] da problemas, forzamos 'mysql'
            'host'      => $config['host'],
            'name'      => $config['database'],
            'user'      => $config['username'],
            'pass'      => $config['password'],
            'port'      => $config['port'],
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            // --- ESTA ES LA LÍNEA MÁGICA PARA TIDB EN RENDER ---
            'mysql_attr_ssl_ca' => '/etc/ssl/certs/ca-certificates.crt',
        ],
    ],
];