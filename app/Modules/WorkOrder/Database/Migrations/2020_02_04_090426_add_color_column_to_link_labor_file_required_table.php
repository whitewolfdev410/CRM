<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddColorColumnToLinkLaborFileRequiredTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $columns = Schema::getColumnListing('link_labor_file_required');

        Schema::table(
            'link_labor_file_required',
            function (Blueprint $table) use ($columns) {
                if (!in_array('color', $columns)) {
                    $table->string('color', 10)
                    ->nullable()
                    ->after('required');
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
    }
}
