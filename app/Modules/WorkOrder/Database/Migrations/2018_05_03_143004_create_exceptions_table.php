<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExceptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('exceptions')) {
            Schema::create('exceptions', function (Blueprint $table) {
                $table->increments('exceptions');
            });
        }

        $columns = Schema::getColumnListing('exceptions');

        Schema::table(
            'exceptions',
            function (Blueprint $table) use ($columns) {
                if (!in_array('exception_id', $columns)) {
                    $table->increments('exception_id');
                }
                if (!in_array('title', $columns)) {
                    $table->string('title', 255)->nullable()
                        ->index('exception_title_i');
                }
                if (!in_array('description', $columns)) {
                    $table->string('description', 255)->nullable()
                        ->index('exception_description_i');
                }
                if (!in_array('data', $columns)) {
                    $table->string('data', 512)->nullable();
                }
                if (!in_array('table_name', $columns)) {
                    $table->string('table_name', 255)->nullable()
                        ->index('exception_table_name_i');
                }
                if (!in_array('record_id', $columns)) {
                    $table->integer('record_id')->unsigned()->nullable()
                        ->index('exception_record_id_i');
                    ;
                }
                if (!in_array('date', $columns)) {
                    $table->date('date')->nullable();
                }
                if (!in_array('is_resolved', $columns)) {
                    $table->integer('is_resolved')->unsigned()->nullable();
                }

                if (!in_array('resolution_type_id', $columns)) {
                    $table->integer('resolution_type_id')->unsigned()->nullable()
                        ->index('exception_resolution_type_id_i');
                    ;
                }
                if (!in_array('resolution_memo', $columns)) {
                    $table->text('resolution_memo')->nullable();
                }
                if (!in_array('created_at', $columns)) {
                    $table->dateTime('created_at')->nullable()->index('created_at');
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
