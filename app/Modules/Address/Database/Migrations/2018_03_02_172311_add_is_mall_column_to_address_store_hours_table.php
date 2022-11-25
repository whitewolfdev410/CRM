<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddIsMallColumnToAddressStoreHoursTable extends Migration
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
                if (!in_array('is_mall', $columns)) {
                    $table->boolean('is_mall')->default(0);
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
