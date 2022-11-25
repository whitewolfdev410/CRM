<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWorkOrderExtensionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('work_order_extension')) {
            Schema::create('work_order_extension', function (Blueprint $table) {
                $table->increments('work_order_extension_id');
            });
        }

        $columns = Schema::getColumnListing('work_order_extension');

        Schema::table(
            'work_order_extension',
            function (Blueprint $table) use ($columns) {
                if (!in_array('work_order_extension_id', $columns)) {
                    $table->increments('work_order_extension_id');
                }
                if (!in_array('person_id', $columns)) {
                    $table->integer('person_id')->unsigned()->nullable()->index('person_id');
                }
                if (!in_array('reason', $columns)) {
                    $table->string('reason')->nullable();
                }
                if (!in_array('created_date', $columns)) {
                    $table->dateTime('created_date')->nullable();
                }
                if (!in_array('extended_date', $columns)) {
                    $table->dateTime('extended_date')->nullable();
                }
                if (!in_array('work_order_id', $columns)) {
                    $table->integer('work_order_id')->unsigned()->nullable()->index('work_order_extension_fk2');
                }
                if (!in_array('updated_at', $columns)) {
                    $table->timestamp('updated_at');
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
