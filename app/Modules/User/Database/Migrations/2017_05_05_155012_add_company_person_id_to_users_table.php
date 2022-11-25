<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCompanyPersonIdToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tableName = 'users';

        if (Schema::hasTable($tableName)) {
            $columns = Schema::getColumnListing($tableName);
            Schema::table(
                $tableName,
                function (Blueprint $table) use ($columns) {
                    if (!in_array('company_person_id', $columns)) {
                        $table
                            ->integer('company_person_id')
                            ->unsigned()
                            ->nullable()
                            ->index('company_person_id');
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
