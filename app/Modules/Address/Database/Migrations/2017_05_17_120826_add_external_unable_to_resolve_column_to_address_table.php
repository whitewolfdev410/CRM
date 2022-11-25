<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddExternalUnableToResolveColumnToAddressTable extends Migration
{
    /**
     * Run the migrations.
     * Create external unable to resolve column
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('address')) {
            $columns = Schema::getColumnListing('address');
            Schema::table('address', function (Blueprint $table) use ($columns) {
                if (!in_array('external_unable_to_resolve', $columns)) {
                    $table->boolean('external_unable_to_resolve')->default(0)->nullable();
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
