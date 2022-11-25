<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLinkVendorAddressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'link_vendor_address_id';
        $tableName = 'link_vendor_address';
    
        if (!Schema::hasTable($tableName)) {
            Schema::create(
                $tableName,
                function (Blueprint $table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );
        }

        $columns = Schema::getColumnListing($tableName);
        Schema::table(
            'link_vendor_address',
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array($primaryKeyName, $columns)) {
                    $table->increments($primaryKeyName);
                }
                
                if (!in_array('address_id', $columns)) {
                    $table->integer('address_id')
                        ->default(0)
                        ->index('address_id');
                }

                if (!in_array('vendor_person_id', $columns)) {
                    $table->integer('vendor_person_id')
                        ->default(0)
                        ->index('vendor_person_id');
                }

                if (!in_array('trade_type_id', $columns)) {
                    $table->integer('trade_type_id')
                        ->nullable()
                        ->index('trade_type_id');
                }

                if (!in_array('rank', $columns)) {
                    $table->integer('rank')
                        ->default(0)
                        ->index('rank');
                }

                if (!in_array('date_created', $columns)) {
                    $table->dateTime('date_created')
                        ->nullable();
                }
                if (!in_array('date_modified', $columns)) {
                    $table->dateTime('date_modified')
                        ->nullable();
                }
            }
        );
    }

    /**
     * Reverse the migration.
     *
     * @return void
     */
    public function down()
    {
        /* we need to assume everything could exist so cannot reverse it */
    }
}
