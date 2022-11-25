<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddIsDeletedColumnToLinkLaborWo extends Migration
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
            if (!in_array('is_deleted', $columns)) {
                $table->boolean('is_deleted')->after('unit_price')->default(false);
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
