<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFleetmaticsColumnFlagTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('link_person_wo')) {
            $columns = Schema::getColumnListing('link_person_wo');

            Schema::table(
                'link_person_wo',
                function (Blueprint $table) use ($columns) {
                    if (!in_array('sent_to_fleetmatics_date', $columns)) {
                        $table->dateTime('sent_to_fleetmatics_date')->nullable();
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
