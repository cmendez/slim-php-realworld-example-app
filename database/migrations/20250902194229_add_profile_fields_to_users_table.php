<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddProfileFieldsToUsersTable extends AbstractMigration
{
    /**
     * Método UP: Se ejecuta al migrar.
     * Dividimos la operación en dos save() para que TiDB procese ordenadamente.
     */
    public function up(): void
    {
        $table = $this->table('users');
        
        // Paso 1: Crear twitter_url
        // Verificamos si no existe para evitar errores si se corrió a medias
        if (!$table->hasColumn('twitter_url')) {
            $table->addColumn('twitter_url', 'string', ['null' => true, 'after' => 'image'])
                  ->save(); // <-- Guardamos inmediatamente para confirmar su creación
        }

        // Paso 2: Crear linkedin_url
        // Ahora sí podemos usar 'after' => 'twitter_url' porque la columna ya existe físicamente
        if (!$table->hasColumn('linkedin_url')) {
            $table->addColumn('linkedin_url', 'string', ['null' => true, 'after' => 'twitter_url'])
                  ->save();
        }
    }

    /**
     * Método DOWN: Se ejecuta al hacer rollback via consola (si fuera necesario).
     */
    public function down(): void
    {
        $table = $this->table('users');
        
        if ($table->hasColumn('linkedin_url')) {
            $table->removeColumn('linkedin_url');
        }

        if ($table->hasColumn('twitter_url')) {
            $table->removeColumn('twitter_url');
        }
        
        $table->save();
    }
}