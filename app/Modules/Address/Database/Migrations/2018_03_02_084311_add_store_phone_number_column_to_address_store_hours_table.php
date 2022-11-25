<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddStorePhoneNumberColumnToAddressStoreHoursTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('address_store_hours')) {
            $columns = Schema::getColumnListing('address_store_hours');
            Schema::table('address_store_hours', function (Blueprint $table) use ($columns) {
                if (!in_array('store_phone_number', $columns)) {
                    $table->string('store_phone_number', 30)->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
