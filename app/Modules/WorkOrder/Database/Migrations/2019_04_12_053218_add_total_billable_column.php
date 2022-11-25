<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTotalBillableColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'work_order_id';

        $columns = Schema::getColumnListing('work_order');

        Schema::table(
            'work_order',
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array('total_billable', $columns)) {
                    $table->float('total_billable')->default(0);
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
