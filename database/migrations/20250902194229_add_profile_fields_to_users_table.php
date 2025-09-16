<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddProfileFieldsToUsersTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('users');
        $table->addColumn('twitter_url', 'string', ['null' => true, 'after' => 'image'])
            ->addColumn('linkedin_url', 'string', ['null' => true, 'after' => 'twitter_url'])
            ->save(); // Usamos save() para actualizar una tabla existente
    }
}