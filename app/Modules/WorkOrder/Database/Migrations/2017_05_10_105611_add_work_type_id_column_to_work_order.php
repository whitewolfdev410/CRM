<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddWorkTypeIdColumnToWorkOrder extends Migration
{
    /**
     * Run the migrations.
     * This column was missed in MGM database
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
                    if (!in_array('wo_type_id', $columns)) {
                        $table->unsignedInteger('wo_type_id')->nullable()->index();
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
