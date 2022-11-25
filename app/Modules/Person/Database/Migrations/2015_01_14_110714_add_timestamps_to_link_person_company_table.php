<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTimestampsToLinkPersonCompanyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('link_person_company')) {
            Schema::table('link_person_company', function (Blueprint $table) {
                if (!Schema::hasColumn('link_person_company', 'created_at')
                    && !Schema::hasColumn('link_person_company', 'updated_at')
                ) {
                    $table->timestamps();
                }
            });
        }
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
