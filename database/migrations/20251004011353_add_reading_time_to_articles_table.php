<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddReadingTimeToArticlesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('articles');
        $table->addColumn('reading_time', 'integer', [
            'null' => true,
            'default' => null,
            'after' => 'publish_date'
        ])->update();
    }
}
