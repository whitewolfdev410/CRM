<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateWorkOrderLiveActionTable2 extends Migration
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
                if (!in_array('number_of_updates', $columns)) {
                    $table->unsignedInteger('number_of_updates')->nullable()->default(0);
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
