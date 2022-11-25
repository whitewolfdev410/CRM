<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddWorkTypeColumnToWorkOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('work_order')) {
            $columns = Schema::getColumnListing('work_order');

            Schema::table(
                'work_order',
                function (Blueprint $table) use ($columns) {
                    if (!in_array('work_type', $columns)) {
                        $table->string('work_type', 500)->nullable();
                    }
                }
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
