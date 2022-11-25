<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddCurrencyPhonePrefixToCountriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tableName = 'countries';
        if (Schema::hasTable($tableName)) {
            $columns = Schema::getColumnListing($tableName);
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                if (!in_array('phone_prefix', $columns)) {
                    $table
                        ->string('phone_prefix', 10)
                        ->after('name');
                }

                if (!in_array('currency', $columns)) {
                    $table
                        ->string('currency', 5)
                        ->nullable()
                        ->after('phone_prefix');
                    $table
                        ->foreign('currency', 'countries_currency_foreign')
                        ->references('code')->on('currencies');
                }
            });
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
