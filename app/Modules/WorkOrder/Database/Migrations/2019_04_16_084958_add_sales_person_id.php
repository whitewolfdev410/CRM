<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSalesPersonId extends Migration
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
                if (!in_array('sales_person_id', $columns)) {
                    $table->integer('sales_person_id')->unsigned()->nullable();
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
