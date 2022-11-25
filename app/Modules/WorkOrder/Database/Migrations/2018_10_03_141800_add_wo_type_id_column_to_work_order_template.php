<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddWoTypeIdColumnToWorkOrderTemplate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('work_order_template')) {
            $columns = Schema::getColumnListing('work_order_template');
            Schema::table('work_order_template', function (Blueprint $table) use ($columns) {
                if (!in_array('wo_type_id', $columns)) {
                    $table->integer('wo_type_id')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
