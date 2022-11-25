<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWorkOrderLiveActionToOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('work_order_live_action_to_order')) {
            Schema::create('work_order_live_action_to_order', function (Blueprint $table) {
                $table->increments('work_order_live_action_to_order_id');
            });
        }

        $columns = Schema::getColumnListing('work_order_live_action_to_order');

        Schema::table(
            'work_order_live_action_to_order',
            function (Blueprint $table) use ($columns) {
                if (!in_array('work_order_live_action_to_order_id', $columns)) {
                    $table->increments('work_order_live_action_to_order_id');
                }

                if (!in_array('work_order_live_action_id', $columns)) {
                    $table->unsignedInteger('work_order_live_action_id')->nullable()->index();
                }

                if (!in_array('link_person_wo_id', $columns)) {
                    $table->unsignedInteger('link_person_wo_id')->nullable()->index();
                }

                if (!in_array('truck_order_id', $columns)) {
                    $table->unsignedInteger('truck_order_id')->nullable()->index();
                }

                if (!in_array('address_id', $columns)) {
                    $table->unsignedInteger('address_id')->nullable()->index();
                }

                if (!in_array('action_type', $columns)) {
                    $table->string('action_type', 20)->nullable();
                }

                if (!in_array('created_at', $columns)) {
                    $table->dateTime('created_at')->nullable();
                }

                if (!in_array('updated_at', $columns)) {
                    $table->dateTime('updated_at')->nullable();
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
