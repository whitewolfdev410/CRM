<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddExpireAtColumnToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('users')) {
            $columns = Schema::getColumnListing('users');
            Schema::table(
                'users',
                function (Blueprint $table) use ($columns) {
                    if (!in_array('expire_at', $columns)) {
                        $table
                            ->dateTime('expire_at')
                            ->nullable();
                    }

                    if (!in_array('is_password_temporary', $columns)) {
                        $table
                            ->boolean('is_password_temporary')
                            ->default(false);
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
