<?php

use Illuminate\Database\Schema\Blueprint;

class AddReadingTimeToArticlesTable extends BaseMigration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->schema->table('articles', function (Blueprint $table) {
            $table->integer('reading_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->schema->table('articles', function (Blueprint $table) {
            $table->dropColumn('reading_time');
        });
    }
}
