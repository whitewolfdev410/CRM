<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVehicleNotificationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('vehicle_notification')) {
            Schema::create('vehicle_notification', function (Blueprint $table) {
                $table->increments('vehicle_notification_id');
            });
        }

        $columns = Schema::getColumnListing('vehicle_notification');

        Schema::table(
            'vehicle_notification',
            function (Blueprint $table) use ($columns) {
                if (!in_array('vehicle_notification_id', $columns)) {
                    $table->increments('vehicle_notification_id');
                }

                if (!in_array('vehicle_number', $columns)) {
                    $table->string('vehicle_number', 20)->index();
                }

                if (!in_array('event', $columns)) {
                    $table->string('event', 100)->index();
                }

                if (!in_array('timestamp', $columns)) {
                    $table->timestamp('timestamp')->index();
                }

                if (!in_array('vehicle_gps_stop_id', $columns)) {
                    $table->integer('vehicle_gps_stop_id')->unsigned()->nullable()->index();
                }

                if (!in_array('truck_order_id', $columns)) {
                    $table->integer('truck_order_id')->unsigned()->nullable()->index();
                }

                if (!in_array('route_id', $columns)) {
                    $table->integer('route_id')->unsigned()->nullable()->index();
                }

                if (!in_array('address_id', $columns)) {
                    $table->integer('address_id')->unsigned()->nullable()->index();
                }

                if (!in_array('type', $columns)) {
                    $table->string('type', 20)->index();
                }

                if (!in_array('sent_at', $columns)) {
                    $table->timestamp('sent_at')->nullable()->index();
                }

                if (!in_array('sent_to', $columns)) {
                    $table->string('sent_to')->nullable();
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
