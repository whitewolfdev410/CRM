<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUserGeocodedToAddressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('address')) {
            if (!Schema::hasColumn('address', 'user_geocoded')) {
                Schema::table('address', function (Blueprint $table) {
                    $table->tinyInteger('user_geocoded', false, true)->default(0);
                });
            }
        }
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
