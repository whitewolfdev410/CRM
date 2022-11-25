<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAddressStoreHoursTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('address_store_hours')) {
            Schema::create('address_store_hours', function (Blueprint $table) {
                $table->integer('address_id');
                $table->string('monday_open_at', 5)->nullable();
                $table->string('monday_close_at', 5)->nullable();
                $table->string('tuesday_open_at', 5)->nullable();
                $table->string('tuesday_close_at', 5)->nullable();
                $table->string('wednesday_open_at', 5)->nullable();
                $table->string('wednesday_close_at', 5)->nullable();
                $table->string('thursday_open_at', 5)->nullable();
                $table->string('thursday_close_at', 5)->nullable();
                $table->string('friday_open_at', 5)->nullable();
                $table->string('friday_close_at', 5)->nullable();
                $table->string('saturday_open_at', 5)->nullable();
                $table->string('saturday_close_at', 5)->nullable();
                $table->string('sunday_open_at', 5)->nullable();
                $table->string('sunday_close_at', 5)->nullable();
                $table->boolean('saturday_is_open')->default(0);
                $table->boolean('sunday_is_open')->default(0);
                $table->timestamp('created_at');
                $table->timestamp('updated_at');

                $table->primary('address_id');
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
