<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddGeocodingDataFieldsToAddressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('address')) {
            if (!Schema::hasColumn('address', 'geocoding_data')) {
                Schema::table('address', function (Blueprint $table) {
                    $table->text('geocoding_data');
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
