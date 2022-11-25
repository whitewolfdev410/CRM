<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAddressInfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('address_info')) {
            Schema::create('address_info', function (Blueprint $table) {
                $table->increments('id');
                $table->string('address_name', 100)->default('')->index('address_name');
                $table->string('location', 255)->nullable();
                $table->longText('json_object');
                $table->string('status');
                $table->timestamp('created_at');
                $table->timestamp('updated_at');
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
