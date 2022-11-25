<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVehicleGpsActionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('vehicle_gps_action')) {
            Schema::create('vehicle_gps_action', function (Blueprint $table) {
                $table->increments('vehicle_gps_action_id');
            });
        }

        $columns = Schema::getColumnListing('vehicle_gps_action');

        Schema::table(
            'vehicle_gps_action',
            function (Blueprint $table) use ($columns) {
                if (!in_array('vehicle_gps_action_id', $columns)) {
                    $table->increments('vehicle_gps_action_id');
                }

                if (!in_array('vehicle_number', $columns)) {
                    $table->string('vehicle_number', 20)->nullable()->index();
                }

                if (!in_array('vehicle_name', $columns)) {
                    $table->string('vehicle_name', 30)->nullable();
                }

                if (!in_array('action_timestamp', $columns)) {
                    $table->timestamp('action_timestamp')->index();
                }

                if (!in_array('display_state', $columns)) {
                    $table->string('display_state', 20)->nullable();
                }

                if (!in_array('latitude', $columns)) {
                    $table->string('latitude', 20)->nullable();
                }

                if (!in_array('longitude', $columns)) {
                    $table->string('longitude', 20)->nullable();
                }

                if (!in_array('address_line1', $columns)) {
                    $table->string('address_line1', 50)->nullable();
                }

                if (!in_array('address_line2', $columns)) {
                    $table->string('address_line2', 50)->nullable();
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
                    $table->decimal('delta_distance', 12, 4)->nullable();
                }

                if (!in_array('delta_time', $columns)) {
                    $table->integer('delta_time')->nullable();
                }

                if (!in_array('direction', $columns)) {
                    $table->integer('direction')->nullable();
                }

                if (!in_array('heading', $columns)) {
                    $table->string('heading', 100)->nullable();
                }

                if (!in_array('driver_number', $columns)) {
                    $table->string('driver_number', 30)->nullable();
                }

                if (!in_array('driver_name', $columns)) {
                    $table->string('driver_name', 30)->nullable();
                }

                if (!in_array('geo_fence_name', $columns)) {
                    $table->string('geo_fence_name', 100)->nullable();
                }

                if (!in_array('speed', $columns)) {
                    $table->decimal('speed', 8, 2)->nullable();
                }

                if (!in_array('idle_time', $columns)) {
                    $table->integer('idle_time')->nullable();
                }

                if (!in_array('engine_minutes', $columns)) {
                    $table->integer('engine_minutes')->nullable();
                }

                if (!in_array('current_odometer', $columns)) {
                    $table->decimal('current_odometer', 16, 8)->nullable();
                }

                if (!in_array('is_private', $columns)) {
                    $table->boolean('is_private')->nullable();
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
