<?php
use Phinx\Migration\AbstractMigration;

class AddPopularityScoreToArticles extends AbstractMigration
{
    public function change()
    {
        $this->table('articles')
            ->addColumn('popularity_score', 'integer', ['default' => 0, 'null' => false])
            ->addIndex(['popularity_score'])   // para ordenar rápido
            ->update();
    }
}
