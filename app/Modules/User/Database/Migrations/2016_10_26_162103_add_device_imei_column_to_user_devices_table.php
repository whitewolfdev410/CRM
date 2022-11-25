<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddDeviceImeiColumnToUserDevicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'user_devices_id';
        $tableName = 'user_devices';

        if (!Schema::hasTable($tableName)) {
            Schema::create(
                $tableName,
                function (Blueprint $table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );
        }

        $columns = Schema::getColumnListing($tableName);

        Schema::table(
            $tableName,
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array('device_imei', $columns)) {
                    $table
                        ->string('device_imei', 64)
                        ->nullable();
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
        /* we need to assume everything could exist so cannot reverse it */
    }
}
