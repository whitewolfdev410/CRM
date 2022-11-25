<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMissingColumnsToServiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('service')) {
            Schema::table('service', function (Blueprint $table) {
                if (!Schema::hasColumn('service', 'service_id')) {
                    $table->increments('service_id');
                }
                if (!Schema::hasColumn('service', 'service_name')) {
                    $table->string('service_name', 48)->default('');
                }

                if (!Schema::hasColumn('service', 'enabled')) {
                    $table->boolean('enabled')->nullable()->default(1);
                }

                if (!Schema::hasColumn('service', 'short_description')) {
                    $table->string('short_description', 100)->nullable();
                }

                if (!Schema::hasColumn('service', 'long_description')) {
                    $table->string('long_description')->nullable();
                }

                if (!Schema::hasColumn('service', 'unit')) {
                    $table->integer('unit', false, true);
                }

                if (!Schema::hasColumn('service', 'category_type_id')) {
                    $table->integer('category_type_id')->unsigned()->nullable()
                        ->default(0);
                }
                if (!Schema::hasColumn('service', 'date_created')) {
                    $table->dateTime('date_created')->nullable()
                        ->nullable();
                }

                if (!Schema::hasColumn('service', 'date_modified')) {
                    $table->dateTime('date_modified')->nullable()
                        ->nullable();
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
        // don't remove columns
    }
}
