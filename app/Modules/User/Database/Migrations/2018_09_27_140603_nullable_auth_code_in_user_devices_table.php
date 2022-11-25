<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class NullableAuthCodeInUserDevicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'id';
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
            function (Blueprint $table) use ($columns) {
                if (in_array('auth_code', $columns)) {
                    $table->string('auth_code', 20)->nullable()->change();
                } else {
                    $table->string('auth_code', 20)->nullable();
                }

                if (in_array('code_date', $columns)) {
                    $table->dateTime('code_date')->nullable()->change();
                } else {
                    $table->dateTime('code_date')->nullable();
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
