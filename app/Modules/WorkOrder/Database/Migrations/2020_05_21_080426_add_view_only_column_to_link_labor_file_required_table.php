<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddViewOnlyColumnToLinkLaborFileRequiredTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $columns = Schema::getColumnListing('link_labor_file_required');

        Schema::table('link_labor_file_required', function (Blueprint $table) use ($columns) {
            if (!in_array('view_only', $columns)) {
                $table->boolean('view_only')
                    ->default(0)
                    ->after('required');
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
    }
}
