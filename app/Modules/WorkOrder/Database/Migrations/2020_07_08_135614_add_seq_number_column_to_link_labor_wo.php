<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddSeqNumberColumnToLinkLaborWo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $columns = Schema::getColumnListing('link_labor_wo');

        Schema::table('link_labor_wo', function (Blueprint $table) use ($columns) {
            if (!in_array('seq_number', $columns)) {
                $table->string('seq_number')->after('description')->nullable();
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
