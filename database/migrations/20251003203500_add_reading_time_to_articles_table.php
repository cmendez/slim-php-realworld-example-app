<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class AddReadingTimeToArticlesTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('articles');
        $table->addColumn('reading_time', 'integer', ['null' => true, 'default' => null])
              ->update();
    }
}