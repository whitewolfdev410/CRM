<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddAddressName2ToAddressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('address')) {
            $columns = Schema::getColumnListing('address');
            Schema::table('address', function (Blueprint $table) use ($columns) {
                if (!in_array('address_name2', $columns)) {
                    $table->string('address_name2', 255)->nullable();
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
