<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMissingColumnsToTypeTable extends Migration
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
                if (!in_array('type_id', $columns)) {
                    $table->increments('type_id');
                }
                if (!in_array('type', $columns)) {
                    $table->string('type', 128)->default('')->index('type');
                }
                if (!in_array('type_value', $columns)) {
                    $table->string('type_value', 32)->index('type_value');
                }
                if (!in_array('sub_type_id', $columns)) {
                    $table->integer('sub_type_id')->unsigned()->default(0);
                }
                if (!in_array('color', $columns)) {
                    $table->string('color', 8)->nullable()->default('#FFFFFF');
                }
                if (!in_array('orderby', $columns)) {
                    $table->integer('orderby')->nullable();
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
