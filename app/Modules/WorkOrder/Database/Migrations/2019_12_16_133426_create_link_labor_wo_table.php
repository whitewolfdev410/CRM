<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLinkLaborWoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('link_labor_wo')) {
            Schema::create('link_labor_wo', function (Blueprint $table) {
                $table->increments('link_labor_wo_id');
            });
        }

        $columns = Schema::getColumnListing('link_labor_wo');

        Schema::table(
            'link_labor_wo',
            function (Blueprint $table) use ($columns) {
                if (!in_array('link_labor_wo_id', $columns)) {
                    $table->increments('link_labor_wo_id');
                }
                if (!in_array('work_order_id', $columns)) {
                    $table->integer('work_order_id')->unsigned()->index('work_order_id_i');
                }
                if (!in_array('person_id', $columns)) {
                    $table->integer('person_id')->unsigned()->index('person_id_i');
                }
                if (!in_array('inventory_id', $columns)) {
                    $table->string('inventory_id', 255)->nullable();
                }
                if (!in_array('name', $columns)) {
                    $table->string('name', 255)->nullable();
                }
                if (!in_array('description', $columns)) {
                    $table->string('description', 255)->nullable();
                }
                if (!in_array('quantity', $columns)) {
                    $table->integer('quantity')->default(0);
                }
                if (!in_array('quantity_from_sl', $columns)) {
                    $table->integer('quantity_from_sl')->nullable()->default(0);
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
