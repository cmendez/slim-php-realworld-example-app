<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPopularityScoreToArticlesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('articles');
        $table->addColumn('popularity_score', 'integer', [
            'default' => 0,
            'null' => false,
            'after' => 'reading_time',
        ])->addIndex(['popularity_score'])
          ->update();
    }
}
