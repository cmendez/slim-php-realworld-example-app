<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddImageToArticlesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('articles')
            ->addColumn('image', 'text', [
                'null' => true,
                'after' => 'publish_date',
            ])
            ->update();
    }
}
