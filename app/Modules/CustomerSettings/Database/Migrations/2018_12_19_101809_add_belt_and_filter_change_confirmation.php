<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddBeltAndFilterChangeConfirmation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('customer_settings')) {
            $columns = Schema::getColumnListing('customer_settings');
            Schema::table(
                'customer_settings',
                function (Blueprint $table) use ($columns) {
                    if (!in_array('belt_change_confirmation', $columns)) {
                        $table->boolean('belt_change_confirmation')->default(1);
                    }

                    if (!in_array('filter_change_confirmation', $columns)) {
                        $table->boolean('filter_change_confirmation')->default(0);
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
    }
}
