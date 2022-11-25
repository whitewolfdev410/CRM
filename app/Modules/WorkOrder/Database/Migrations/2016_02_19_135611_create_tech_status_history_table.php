<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTechStatusHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('tech_status_history')) {
            Schema::create('tech_status_history', function (Blueprint $table) {
                $table->increments('id');
            });
        }

        $columns = Schema::getColumnListing('tech_status_history');

        Schema::table(
            'tech_status_history',
            function (Blueprint $table) use ($columns) {
                if (!in_array('id', $columns)) {
                    $table->increments('id');
                }

                if (!in_array('link_person_wo_id', $columns)) {
                    $table->integer('link_person_wo_id')
                        ->unsigned()
                        ->index('link_person_wo_id_i');
                }

                if (!in_array('previous_tech_status_type_id', $columns)) {
                    $table->integer('previous_tech_status_type_id')
                        ->unsigned()
                        ->index();
                }

                if (!in_array('current_tech_status_type_id', $columns)) {
                    $table->integer('current_tech_status_type_id')
                        ->unsigned()
                        ->index();
                }

                if (!in_array('changed_at', $columns)) {
                    $table->dateTime('changed_at');
                }

                if (!in_array('created_at', $columns)) {
                    $table->dateTime('created_at');
                }

                if (!in_array('updated_at', $columns)) {
                    $table->dateTime('updated_at')->nullable();
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
    }
}
