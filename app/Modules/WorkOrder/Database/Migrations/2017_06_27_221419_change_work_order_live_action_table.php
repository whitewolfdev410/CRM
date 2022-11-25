<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeWorkOrderLiveActionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('work_order_live_action')) {
            Schema::create('work_order_live_action', function (Blueprint $table) {
                $table->increments('work_order_live_action_id');
            });
        }

        $columns = Schema::getColumnListing('work_order_live_action');

        Schema::table(
            'work_order_live_action',
            function (Blueprint $table) use ($columns) {
                if (in_array('action_date', $columns)) {
                    $table->renameColumn('action_date', 'action_date_from');
                }

                if (!in_array('action_date_to', $columns)) {
                    $table->dateTime('action_date_to')->nullable()->index();
                }

                if (in_array('odometer', $columns)) {
                    $table->renameColumn('odometer', 'odometer_from');
                }

                if (!in_array('odometer_to', $columns)) {
                    $table->float('odometer_to', 12)->nullable();
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
