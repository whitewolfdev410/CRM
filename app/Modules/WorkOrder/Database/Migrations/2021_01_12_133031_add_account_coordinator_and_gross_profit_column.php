<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAccountCoordinatorAndGrossProfitColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $columns = Schema::getColumnListing('work_order');

        Schema::table('work_order', function (Blueprint $table) use ($columns) {
            if (!in_array('gross_profit', $columns)) {
                $table->text('gross_profit')->nullable();
            }
            if (!in_array('account_coordinator_person_id', $columns)) {
                $table->integer('account_coordinator_person_id')->unsigned()->nullable();
            }
        });
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
