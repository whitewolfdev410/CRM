<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddAddressIdColumnToAddressInfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('address_info')) {
            $columns = Schema::getColumnListing('address_info');
            Schema::table('address_info', function (Blueprint $table) use ($columns) {
                if (!in_array('address_id', $columns)) {
                    $table->integer('address_id')->nullable();
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
