<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWorkOrderActionTable extends Migration
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
                if (!in_array('work_order_action_id', $columns)) {
                    $table->increments('work_order_action_id');
                }

                if (!in_array('work_order_id', $columns)) {
                    $table->unsignedInteger('work_order_id')->index();
                }

                if (!in_array('truck_order_id', $columns)) {
                    $table->unsignedInteger('truck_order_id')->nullable()->index();
                }

                if (!in_array('vehicle_name', $columns)) {
                    $table->string('vehicle_name');
                }

                if (!in_array('start_location', $columns)) {
                    $table->string('start_location')->nullable()->index();
                }

                if (!in_array('stop_location', $columns)) {
                    $table->string('stop_location')->nullable()->index();
                }

                if (!in_array('travel_time_seconds', $columns)) {
                    $table->unsignedInteger('travel_time_seconds')->nullable();
                }

                if (!in_array('time_there_seconds', $columns)) {
                    $table->unsignedInteger('time_there_seconds')->nullable();
                }

                if (!in_array('idle_time_seconds', $columns)) {
                    $table->unsignedInteger('idle_time_seconds')->nullable();
                }

                if (!in_array('distance', $columns)) {
                    $table->unsignedInteger('distance')->nullable();
                }

                if (!in_array('odometer_start', $columns)) {
                    $table->string('odometer_start')->nullable();
                }

                if (!in_array('odometer_end', $columns)) {
                    $table->string('odometer_end')->nullable();
                }

                if (!in_array('start_at', $columns)) {
                    $table->dateTime('start_at')->nullable();
                }

                if (!in_array('arrival_at', $columns)) {
                    $table->dateTime('arrival_at')->nullable();
                }

                if (!in_array('departure_at', $columns)) {
                    $table->dateTime('departure_at')->nullable();
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
