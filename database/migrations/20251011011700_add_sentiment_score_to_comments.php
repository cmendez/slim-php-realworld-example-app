<?php
use Phinx\Migration\AbstractMigration;

class AddSentimentScoreToComments extends AbstractMigration
{
    public function change()
    {
        $this->table('comments')
            ->addColumn('sentiment_score', 'integer', ['default' => 0, 'null' => false])
            ->update();
    }
}
