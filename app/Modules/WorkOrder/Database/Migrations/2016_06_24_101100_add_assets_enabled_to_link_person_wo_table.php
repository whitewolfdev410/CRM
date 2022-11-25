<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAssetsEnabledToLinkPersonWoTable extends Migration
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
                    if (!in_array('assets_enabled', $columns)) {
                        $table->boolean('assets_enabled')
                            ->default(true);
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
