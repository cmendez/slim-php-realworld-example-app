<?php

namespace Conduit\Services\Database;

use Illuminate\Database\Capsule\Manager;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class EloquentServiceProvider implements ServiceProviderInterface
{

    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple A container instance
     */
    public function register(Container $pimple)
    {
        $capsule = new Manager();
        $config = $pimple['settings']['database'];

        // 1. Definimos la configuración base (igual que antes)
        $connectionSettings = [
            'driver'    => $config['driver'],
            'host'      => $config['host'],
            'database'  => $config['database'],
            'username'  => $config['username'],
            'password'  => $config['password'],
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ];

        // 2. NUEVO: Detectamos si estamos en Producción (Render) para activar SSL
        // Esto evita errores en tu local si no tienes los certificados en esa ruta.
        if (getenv('APP_ENV') === 'production') {
            $connectionSettings['options'] = [
                // Esta es la línea que TiDB necesita para aceptar la conexión
                \PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
                // Deshabilitar conexiones persistentes suele ser mejor en contenedores
                \PDO::ATTR_PERSISTENT => false,
            ];
        }

        // 3. Pasamos la configuración final a Eloquent
        $capsule->addConnection($connectionSettings);

        // Make this Capsule instance available globally via static methods... (optional)
        $capsule->setAsGlobal();

        // Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
        $capsule->bootEloquent();


        $pimple['db'] = function ($c) use ($capsule) {
            return $capsule;
        };
    }
}