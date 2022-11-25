<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddMallColumnsToAddressStoreHoursTable extends Migration
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
                if (!in_array('mall_name', $columns)) {
                    $table->string('mall_name', 255)->nullable();
                }

                if (!in_array('mall_phone_number', $columns)) {
                    $table->string('mall_phone_number', 30)->nullable();
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
