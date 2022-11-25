<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWorkOrderLiveActionLocationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('work_order_live_action_location')) {
            Schema::create('work_order_live_action_location', function (Blueprint $table) {
                $table->increments('work_order_live_action_location_id');
            });
        }

        $columns = Schema::getColumnListing('work_order_live_action_location');

        Schema::table(
            'work_order_live_action_location',
            function (Blueprint $table) use ($columns) {
                if (!in_array('work_order_live_action_location_id', $columns)) {
                    $table->increments('work_order_live_action_location_id');
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

                if (!in_array('vehicle_status', $columns)) {
                    $table->string('vehicle_status', 20)->nullable();
                }

                if (!in_array('latitude', $columns)) {
                    $table->string('latitude', 20)->nullable();
                }

                if (!in_array('longitude', $columns)) {
                    $table->string('longitude', 20)->nullable();
                }

                if (!in_array('odometer', $columns)) {
                    $table->float('odometer', 16)->nullable();
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
