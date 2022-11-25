<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTypeTableTypeKey extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('type')) {
            $columns = Schema::getColumnListing('type');
            Schema::table('type', function (Blueprint $table) use ($columns) {
                if (!in_array('type_key', $columns)) {
                    $table->string('type_key', 48)
                        ->after('type_id')
                        ->nullable()
                        ->index();
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
