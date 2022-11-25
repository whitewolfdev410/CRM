<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSalesPersonIdColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'person_id';

        $columns = Schema::getColumnListing('person');

        Schema::table(
            'person',
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array('sales_person_id', $columns)) {
                    $table->integer('sales_person_id')->unsigned()->nullable();
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
