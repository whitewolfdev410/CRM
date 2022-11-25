<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeExpectedCompletionDateIntoDate extends Migration
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
            Schema::table('work_order', function ($table) use ($columns) {
                if (!in_array('expected_completion_date', $columns)) {
                    $table->date('expected_completion_date')->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migration.
     *
     * @return void
     */
    public function down()
    {
        /* we need to assume that it was already correct so cannot reverse it */
    }
}
