<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPopularityScoreToArticlesTable extends AbstractMigration
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
        $table = $this->table('articles');
        $table->addColumn('popularity_score', 'integer', [
            'null' => false,
            'default' => 0,
            'after' => 'reading_time',
            'comment' => 'Popularity score based on favorites and comment sentiment analysis'
        ])
        ->addIndex(['popularity_score'], ['name' => 'idx_articles_popularity_score'])
        ->update();
    }
}
