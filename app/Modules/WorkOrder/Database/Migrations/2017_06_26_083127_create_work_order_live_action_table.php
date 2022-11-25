<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWorkOrderLiveActionTable extends Migration
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
                if (!in_array('work_order_live_action_id', $columns)) {
                    $table->increments('work_order_live_action_id');
                }

                if (!in_array('address_id', $columns)) {
                    $table->unsignedInteger('address_id')->nullable()->index();
                }

                if (!in_array('vehicle_number', $columns)) {
                    $table->string('vehicle_number', 20)->index();
                }

                if (!in_array('vehicle_name', $columns)) {
                    $table->string('vehicle_name', 30)->nullable();
                }

                if (!in_array('truck_order_id', $columns)) {
                    $table->unsignedInteger('truck_order_id')->nullable()->index();
                }

                if (!in_array('address_line_1', $columns)) {
                    $table->string('address_line_1', 100)->nullable();
                }

                if (!in_array('address_line_2', $columns)) {
                    $table->string('address_line_2', 100)->nullable();
                }

                if (!in_array('locality', $columns)) {
                    $table->string('locality', 50)->nullable();
                }

                if (!in_array('administrative_area', $columns)) {
                    $table->string('administrative_area', 10)->nullable();
                }

                if (!in_array('postal_code', $columns)) {
                    $table->string('postal_code', 15)->nullable();
                }

                if (!in_array('country', $columns)) {
                    $table->string('country', 15)->nullable();
                }

                if (!in_array('delta_distance', $columns)) {
                    $table->float('delta_distance', 8, 2)->nullable();
                }

                if (!in_array('delta_time', $columns)) {
                    $table->integer('delta_time')->nullable();
                }

                if (!in_array('vehicle_status', $columns)) {
                    $table->string('vehicle_status', 20)->nullable();
                }

                if (!in_array('latitude', $columns)) {
                    $table->string('latitude', 20)->nullable();
                }

                if (!in_array('longitude', $columns)) {
                    $table->string('longitude', 20)->nullable();
                }

                if (!in_array('action_type', $columns)) {
                    $table->string('action_type', 20)->nullable();
                }

                if (!in_array('speed', $columns)) {
                    $table->integer('speed')->default(0)->nullable();
                }

                if (!in_array('action_date', $columns)) {
                    $table->dateTime('action_date')->nullable()->index();
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
