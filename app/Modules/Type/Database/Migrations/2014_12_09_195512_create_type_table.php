<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('type')) {
            Schema::create('type', function (Blueprint $table) {
                $table->increments('type_id');
                $table->string('type', 128)->default('')->index('type');
                $table->string('type_value', 32)->index('type_value');
                $table->integer('sub_type_id')->unsigned()->default(0);
                $table->string('color', 8)->nullable()->default('#FFFFFF');
                $table->integer('orderby')->nullable();
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
