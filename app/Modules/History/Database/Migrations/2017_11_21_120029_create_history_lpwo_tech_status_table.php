<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHistoryLpwoTechStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('history_lpwo_tech_status')) {
            Schema::create('history_lpwo_tech_status', function (Blueprint $table) {
                $table->increments('id');
            });
        }

        $columns = Schema::getColumnListing('history_lpwo_tech_status');

        Schema::table(
            'history_lpwo_tech_status',
            function (Blueprint $table) use ($columns) {
                $added = false;
                if (!in_array('id', $columns)) {
                    $table->increments('id');
                }
                if (!in_array('link_person_wo_id', $columns)) {
                    $table->integer('link_person_wo_id');
                }
                if (!in_array('type_id', $columns)) {
                    $table->integer('type_id');
                }
                if (!in_array('type_value', $columns)) {
                    $table->string('type_value');
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
                    $table->index('link_person_wo_id');
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
