<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLockedAtColumnToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('users')) {
            $primaryKeyName = 'users_id';
            $columns = Schema::getColumnListing('users');

            Schema::table(
                'users',
                function (Blueprint $table) use ($columns) {
                    if (!in_array('failed_attempts', $columns)) {
                        $table
                            ->integer('failed_attempts')
                            ->default(0);
                    }

                    if (!in_array('last_failed_attempt_at', $columns)) {
                        $table
                            ->dateTime('last_failed_attempt_at')
                            ->nullable();
                    }

                    if (!in_array('locked_at', $columns)) {
                        $table
                            ->dateTime('locked_at')
                            ->nullable();
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
    }
}
