<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddExternalUpdateDateColumnToAddressTable extends Migration
{
    /**
     * Run the migrations.
     * Create external address update column
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('address')) {
            $columns = Schema::getColumnListing('address');
            Schema::table('address', function (Blueprint $table) use ($columns) {
                if (!in_array('date_external_updated', $columns)) {
                    $table->dateTime('date_external_updated')->nullable();
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
