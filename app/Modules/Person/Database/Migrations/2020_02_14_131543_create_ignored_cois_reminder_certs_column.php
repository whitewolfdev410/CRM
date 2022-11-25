<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIgnoredCoisReminderCertsColumn extends Migration
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

        $columns = Schema::getColumnListing($tableName);

        Schema::table(
            $tableName,
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array('ignored_cois_reminder_certs', $columns)) {
                    $table->string('ignored_cois_reminder_certs')->nullable();
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
