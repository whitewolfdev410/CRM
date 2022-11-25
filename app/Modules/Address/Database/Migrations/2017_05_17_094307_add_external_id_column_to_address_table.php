<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddExternalIdColumnToAddressTable extends Migration
{
    /**
     * Run the migrations.
     * Create external address id column
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('address')) {
            $columns = Schema::getColumnListing('address');
            Schema::table('address', function (Blueprint $table) use ($columns) {
                if (!in_array('external_address_id', $columns)) {
                    $table->string('external_address_id', 50)->nullable();
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
