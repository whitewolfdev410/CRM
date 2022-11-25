<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePersonDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'person_data_id';
        $table = 'person_data';

        if (!Schema::hasTable($table)) {
            Schema::create(
                $table,
                function (Blueprint $table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );
        }

        $columns = Schema::getColumnListing($table);

        Schema::table(
            $table,
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array($primaryKeyName, $columns)) {
                    $table->increments($primaryKeyName);
                }

                if (!in_array('person_id', $columns)) {
                    $table
                        ->integer('person_id')
                        ->index('person_id_indx');
                }

                if (!in_array('data_key', $columns)) {
                    $table
                        ->string('data_key', 128)
                        ->index('data_key_indx');
                }

                if (!in_array('data_value', $columns)) {
                    $table
                        ->string('data_value', 128);
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
