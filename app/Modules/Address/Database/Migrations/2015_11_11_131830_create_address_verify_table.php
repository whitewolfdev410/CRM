<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAddressVerifyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'id';

        if (!Schema::hasTable('address_verify')) {
            Schema::create(
                'address_verify',
                function (Blueprint $table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );

            $columns = Schema::getColumnListing('address_verify');

            Schema::table(
                'address_verify',
                function (Blueprint $table) use ($columns, $primaryKeyName) {
                    if (!in_array($primaryKeyName, $columns)) {
                        $table->increments($primaryKeyName);
                    }
                    if (!in_array('zip_code', $columns)) {
                        $table->string('zip_code', 14);
                    }
                    if (!in_array('country', $columns)) {
                        $table->string('country', 5);
                    }

                    if (!in_array('latitude', $columns)) {
                        $table->string('latitude', 15);
                    }

                    if (!in_array('longitude', $columns)) {
                        $table->string('longitude', 15);
                    }

                    if (!in_array('city', $columns)) {
                        $table->string('city', 48);
                    }
                    if (!in_array('state', $columns)) {
                        $table->string('state', 24);
                    }

                    if (!in_array('county', $columns)) {
                        $table->string('county', 48);
                    }

                    $keyExists = DB::select(
                        DB::raw(
                            'SHOW KEYS
        FROM address_verify
        WHERE Key_name=\'zip_country_index\''
                        )
                    );
                    if (empty($keyExists)) {
                        $table->index(['zip_code', 'country'], 'zip_country_index');
                    }
                }
            );
        }
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
