<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddScheduledDateColumnToLinkPersonWoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $table = 'link_person_wo';

        if (Schema::hasTable($table)) {
            $columns = Schema::getColumnListing($table);

            Schema::table(
                'link_person_wo',
                function (Blueprint $table) use ($columns) {
                    if (!in_array('scheduled_date', $columns)) {
                        $table->dateTime('scheduled_date')
                            ->nullable()
                            ->index('scheduled_date_i');
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
