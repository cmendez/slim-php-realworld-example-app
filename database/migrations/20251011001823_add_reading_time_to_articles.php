<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddReadingTimeToArticles extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('articles');

        // 1) Crear columna solo si no existe
        if (!$table->hasColumn('reading_time')) {
            $table->addColumn('reading_time', 'integer', ['null' => true])->update();
        }

        // 2) Backfill según motor
        $adapter = strtolower((string)($this->getAdapter()->getOptions()['adapter'] ?? getenv('DB_CONNECTION') ?? ''));

        if (in_array($adapter, ['pgsql','postgres','postgresql'], true)) {
            $this->execute(<<<'SQL'
                UPDATE articles
                SET reading_time = GREATEST(
                    CEIL( COALESCE(array_length(regexp_split_to_array(COALESCE(body, ''), E'\s+'), 1), 0) / 200.0 ),
                    1
                )::int
                WHERE body IS NOT NULL;
            SQL);
        } else {
            // MySQL/MariaDB
            $this->execute(<<<'SQL'
                UPDATE articles
                SET reading_time = GREATEST(
                    CEIL(
                        (
                            LENGTH(TRIM(COALESCE(body, '')))
                            - LENGTH(REPLACE(TRIM(COALESCE(body, '')), ' ', ''))
                            + CASE WHEN TRIM(COALESCE(body,'')) = '' THEN 0 ELSE 1 END
                        ) / 200
                    ),
                    1
                )
                WHERE body IS NOT NULL;
            SQL);
        }

        // 3) Endurecer (NOT NULL + default 1)
        //    (si ya existe, simplemente la ajusta)
        $this->table('articles')
            ->changeColumn('reading_time', 'integer', ['null' => false, 'default' => 1])
            ->update();
    }

    public function down(): void
    {
        $this->table('articles')->removeColumn('reading_time')->update();
    }
}
