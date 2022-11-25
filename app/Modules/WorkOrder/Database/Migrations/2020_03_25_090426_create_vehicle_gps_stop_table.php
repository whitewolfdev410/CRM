<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVehicleGpsStopTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('vehicle_gps_stop')) {
            Schema::create('vehicle_gps_stop', function (Blueprint $table) {
                $table->increments('vehicle_gps_stop_id');
            });
        }

        $columns = Schema::getColumnListing('vehicle_gps_stop');

        Schema::table(
            'vehicle_gps_stop',
            function (Blueprint $table) use ($columns) {
                if (!in_array('vehicle_gps_stop_id', $columns)) {
                    $table->increments('vehicle_gps_stop_id');
                }

                if (!in_array('vehicle_number', $columns)) {
                    $table->string('vehicle_number', 20)->nullable()->index();
                }

                if (!in_array('vehicle_gps_action_id', $columns)) {
                    $table->integer('vehicle_gps_action_id')->unsigned();
                }

                if (!in_array('matched', $columns)) {
                    $table->boolean('matched')->default(0)->index();
                }

                if (!in_array('exc_note', $columns)) {
                    $table->text('exc_note')->nullable();
                }

                if (!in_array('exc_approved', $columns)) {
                    $table->boolean('exc_approved')->default(0)->index();
                }

                if (!in_array('arrival', $columns)) {
                    $table->timestamp('arrival')->index();
                }

                if (!in_array('departure', $columns)) {
                    $table->timestamp('departure')->index();
                }

                if (!in_array('duration_sec', $columns)) {
                    $table->integer('duration_sec')->unsigned()->index();
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
