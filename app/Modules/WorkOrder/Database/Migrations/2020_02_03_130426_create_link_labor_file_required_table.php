<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLinkLaborFileRequiredTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('link_labor_file_required')) {
            Schema::create('link_labor_file_required', function (Blueprint $table) {
                $table->increments('link_labor_file_required_id');
            });
        }

        $columns = Schema::getColumnListing('link_labor_file_required');

        Schema::table(
            'link_labor_file_required',
            function (Blueprint $table) use ($columns) {
                if (!in_array('customer_settings_id', $columns)) {
                    $table->integer('customer_settings_id')->unsigned()->index();
                }
                if (!in_array('inventory_id', $columns)) {
                    $table->string('inventory_id', 255);
                }
                if (!in_array('required', $columns)) {
                    $table->boolean('required')->default(0);
                }
                if (!in_array('created_at', $columns)) {
                    $table->dateTime('created_at')->nullable();
                }
                if (!in_array('updated_at', $columns)) {
                    $table->dateTime('updated_at')->nullable();
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
