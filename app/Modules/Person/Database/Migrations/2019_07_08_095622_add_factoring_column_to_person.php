<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFactoringColumnToPerson extends Migration
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
                if (!in_array('factoring', $columns)) {
                    $table->boolean('factoring')->default(0);
                }
            }
        );
    }

    /**
     * Reverse the migration.
     *s
     * @return void
     */
    public function down()
    {
        /* we need to assume everything could exist so cannot reverse it */
    }
}
