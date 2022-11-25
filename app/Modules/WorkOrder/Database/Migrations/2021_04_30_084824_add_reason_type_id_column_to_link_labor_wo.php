<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddReasonTypeIdColumnToLinkLaborWo extends Migration
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
            if (!in_array('reason_type_id', $columns)) {
                $table->unsignedInteger('reason_type_id')->after('is_deleted')->nullable();
            }

            if (!in_array('comment', $columns)) {
                $table->string('comment')->after('reason_type_id')->nullable();
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
