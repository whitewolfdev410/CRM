<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHistoryInsertStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('history_insert_status')) {
            Schema::create('history_insert_status', function (Blueprint $table) {
                $table->increments('id');
            });
        }

        $columns = Schema::getColumnListing('history_insert_status');

        Schema::table(
            'history_insert_status',
            function (Blueprint $table) use ($columns) {
                $added = false;
                if (!in_array('id', $columns)) {
                    $table->increments('id');
                }
                if (!in_array('table_name', $columns)) {
                    $table->string('table_name');
                }
                if (!in_array('insert_table', $columns)) {
                    $table->string('insert_table');
                }
                if (!in_array('insert_column', $columns)) {
                    $table->string('insert_column');
                }
                if (!in_array('insert_type', $columns)) {
                    $table->string('insert_type');
                }
                if (!in_array('last_history_id', $columns)) {
                    $table->integer('last_history_id');
                }
                if (!in_array('date_created', $columns)) {
                    $table->dateTime('date_created')
                        ->nullable();
                }
                if (!in_array('date_modified', $columns)) {
                    $table->dateTime('date_modified')
                        ->nullable();
                    $added = true;
                }
                if ($added) {
                    $table->index('last_history_id');
                }
            }
        );
    }

    /**
     * Reverse the migration.
     *
     * @return void
     */
    public function down()
    {
        /* we need to assume everything could exist so cannot reverse it */
    }
}
