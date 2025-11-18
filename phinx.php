<?php

require_once './vendor/autoload.php';

// --- CORRECCIÓN DE ERROR "Class BaseMigration not found" ---
// Cargamos manualmente la clase BaseMigration que está en la carpeta generator
// Usamos __DIR__ para asegurar que la ruta sea absoluta desde la raíz
$baseMigrationPath = __DIR__ . '/database/generator/BaseMigration.php';

if (file_exists($baseMigrationPath)) {
    require_once $baseMigrationPath;
}

// --- CARGA DE DEPENDENCIAS DE SLIM ---
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
        
        // LÓGICA AUTOMÁTICA:
        // Si la variable de entorno APP_ENV es 'production', usa la config de producción (con SSL).
        // Si no, usa 'development' (tu local).
        'default_environment' => getenv('APP_ENV') === 'production' ? 'production' : 'development',
        
        // ENTORNO LOCAL (Docker Desktop)
        'development' => [
            'name'       => $config['database'],
            // Usa la conexión PDO directa que ya creó tu contenedor localmente
            'connection' => $container->get('db')->getConnection()->getPdo(),
        ],
        
        // ENTORNO NUBE (Render + TiDB)
        'production' => [
            'adapter'   => 'mysql',
            'host'      => $config['host'],
            'name'      => $config['database'],
            'user'      => $config['username'],
            'pass'      => $config['password'],
            'port'      => $config['port'],
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            // CONFIGURACIÓN SSL OBLIGATORIA PARA TIDB
            'mysql_attr_ssl_ca' => '/etc/ssl/certs/ca-certificates.crt',
        ],
    ],
];