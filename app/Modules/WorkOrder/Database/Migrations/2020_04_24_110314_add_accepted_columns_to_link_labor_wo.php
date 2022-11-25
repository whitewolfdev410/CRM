<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddAcceptedColumnsToLinkLaborWo extends Migration
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
            if (!in_array('unit_price', $columns)) {
                $table->decimal('unit_price')->after('quantity_from_sl')->nullable();
            }
            
            if (!in_array('accepted_quantity', $columns)) {
                $table->integer('accepted_quantity')->after('unit_price')->nullable();
            }

            if (!in_array('accepted_person_id', $columns)) {
                $table->integer('accepted_person_id')->after('accepted_quantity')->nullable();
            }

            if (!in_array('accepted_at', $columns)) {
                $table->dateTime('accepted_at')->after('accepted_person_id')->nullable();
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
