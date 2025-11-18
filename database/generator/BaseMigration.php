<?php

use Phinx\Migration\AbstractMigration;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Class BaseMigration
 *
 * @package App\Database\Migrations
 */
class BaseMigration extends AbstractMigration
{
    
    /**
     * @var \Illuminate\Database\Schema\Builder
     */
    protected $schema;
    
    public function init()
    {
        // CORRECCIÓN IMPORTANTE:
        // Usamos la llamada estática para aprovechar la conexión global (con SSL)
        // que ya configuramos en el arranque de la app.
        $this->schema = Capsule::schema();
    }
    
}