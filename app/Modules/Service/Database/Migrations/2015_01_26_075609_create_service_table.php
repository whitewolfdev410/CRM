<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateServiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('service')) {
            Schema::create('service', function (Blueprint $table) {
                $table->increments('service_id');
                $table->string('service_name', 48)->default('');
                $table->boolean('enabled')->nullable()->default(1);
                $table->string('short_description', 100)->nullable();
                $table->string('long_description')->nullable();
                $table->integer('unit', false, true);
                $table->integer('category_type_id')->unsigned()->nullable()
                    ->default(0);
                $table->dateTime('date_created')->nullable()
                    ->nullable();
                $table->dateTime('date_modified')->nullable()
                    ->nullable();
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
