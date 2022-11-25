<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPermissionColumnToWorkOrderExtension extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('work_order_extension')) {
            $columns = Schema::getColumnListing('work_order_extension');
            Schema::table('work_order_extension', function (Blueprint $table) use ($columns) {
                if (!in_array('permission', $columns)) {
                    $table->boolean('permission')->default(1)->nullable();
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
        //
    }
}
