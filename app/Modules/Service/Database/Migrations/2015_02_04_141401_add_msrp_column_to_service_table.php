<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMsrpColumnToServiceTable extends Migration
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
                if (!Schema::hasColumn('service', 'msrp')) {
                    $table->decimal('msrp', 10, 2)->after('unit');
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
        // don't remove column - it might existed before migration
    }
}
