<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddMissingColumnsToLinkPersonWo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $columns = Schema::getColumnListing('link_person_wo');

        Schema::table('link_person_wo', function (Blueprint $table) use ($columns) {
            if (!in_array('scheduled_date_simple', $columns)) {
                $table->date('scheduled_date_simple')->after('completed_date')->nullable();
            }

            if (!in_array('is_ghost', $columns)) {
                $table->boolean('is_ghost')->after('scheduled_date_simple')->default(false);
            }

            if (!in_array('is_hard_schedule', $columns)) {
                $table->boolean('is_hard_schedule')->after('is_ghost')->default(false);
            }

            if (!in_array('qb_nte', $columns)) {
                $table->decimal('qb_nte', 8, 2)->after('qb_info')->nullable();
            }

            if (!in_array('qb_ecd', $columns)) {
                $table->date('qb_ecd')->after('qb_nte')->nullable();
            }
        });
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
