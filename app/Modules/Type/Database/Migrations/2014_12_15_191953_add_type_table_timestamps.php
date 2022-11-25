<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTypeTableTimestamps extends Migration
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
                if (!in_array('created_at', $columns) && !in_array('updated_at', $columns)) {
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
