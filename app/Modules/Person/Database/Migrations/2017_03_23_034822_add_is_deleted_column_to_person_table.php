<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddIsDeletedColumnToPersonTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'person_id';
        $tableName = 'person';

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
            $tableName,
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array('is_deleted', $columns)) {
                    $table
                        ->boolean('is_deleted')
                        ->nullable()
                        ->default(0)
                        ->index('is_deleted_i');
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
