<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddServiceKeyColumnToServiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('service')) {
            Schema::table('service', function (Blueprint $table) {
                if (!Schema::hasColumn('service', 'service_key')) {
                    $table->string('service_key', 48, 2)->after('service_name');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // don't remove column - it might existed before migration
    }
}
