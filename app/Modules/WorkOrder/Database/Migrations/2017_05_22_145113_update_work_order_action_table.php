<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateWorkOrderActionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('work_order_action')) {
            Schema::create('work_order_action', function (Blueprint $table) {
                $table->increments('work_order_action_id');
            });
        }

        $columns = Schema::getColumnListing('work_order_action');

        Schema::table(
            'work_order_action',
            function (Blueprint $table) use ($columns) {
                if (!in_array('action_type', $columns)) {
                    $table->string('action_type')->nullable();
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
