<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVehicleGpsStopMatchTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('vehicle_gps_stop_match')) {
            Schema::create('vehicle_gps_stop_match', function (Blueprint $table) {
                $table->increments('vehicle_gps_stop_match_id');
            });
        }

        $columns = Schema::getColumnListing('vehicle_gps_stop_match');

        Schema::table(
            'vehicle_gps_stop_match',
            function (Blueprint $table) use ($columns) {
                if (!in_array('vehicle_gps_stop_match_id', $columns)) {
                    $table->increments('vehicle_gps_stop_match_id');
                }

                if (!in_array('vehicle_gps_stop_id', $columns)) {
                    $table->integer('vehicle_gps_stop_id')->unsigned()->index();
                }

                if (!in_array('type', $columns)) {
                    $table->string('type', 20)->index();
                }

                if (!in_array('name', $columns)) {
                    $table->string('name');
                }

                if (!in_array('truck_order_id', $columns)) {
                    $table->integer('truck_order_id')->unsigned()->nullable()->index();
                }

                if (!in_array('address_id', $columns)) {
                    $table->integer('address_id')->unsigned()->nullable()->index();
                }

                if (!in_array('distance', $columns)) {
                    $table->decimal('distance', 12, 4);
                }

                if (!in_array('latitude', $columns)) {
                    $table->string('latitude', 20)->nullable();
                }

                if (!in_array('longitude', $columns)) {
                    $table->string('longitude', 20)->nullable();
                }
                if (!in_array('address_1', $columns)) {
                    $table->string('address_1', 48)->default('0');
                }
                if (!in_array('address_2', $columns)) {
                    $table->string('address_2', 48)->nullable();
                }
                if (!in_array('city', $columns)) {
                    $table->string('city', 48)->default('0');
                }
                if (!in_array('county', $columns)) {
                    $table->string('county', 28)->default('');
                }
                if (!in_array('state', $columns)) {
                    $table->string('state', 24)->nullable();
                }
                if (!in_array('zip_code', $columns)) {
                    $table->string('zip_code', 14)->default('');
                }
                if (!in_array('country', $columns)) {
                    $table->string('country', 24)->nullable();
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
