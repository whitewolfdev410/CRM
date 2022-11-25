<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPrimaryTechnicianToLinkPersonWoTable extends Migration
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
                    if (!in_array('primary_technician', $columns)) {
                        $table->boolean('primary_technician')
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
        /* we need to assume everything could exist so cannot reverse it */
    }
}
