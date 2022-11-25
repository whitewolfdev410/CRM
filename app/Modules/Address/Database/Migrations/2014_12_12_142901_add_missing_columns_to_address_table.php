<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMissingColumnsToAddressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('address')) {
            $columns = Schema::getColumnListing('address');
            Schema::table('address', function (Blueprint $table) use ($columns) {
                if (!in_array('address_id', $columns)) {
                    $table->increments('address_id');
                }
                if (!in_array('address_1', $columns)) {
                    $table->string('address_1', 48)->default('0');
                }
                if (!in_array('address_2', $columns)) {
                    $table->string('address_2', 48)->nullable();
                }
                if (!in_array('city', $columns)) {
                    $table->string('city', 48)->default('0')->index('city_i');
                }
                if (!in_array('county', $columns)) {
                    $table->string('county', 28)->default('');
                }
                if (!in_array('state', $columns)) {
                    $table->string('state', 24)->nullable()->index('state_index');
                }
                if (!in_array('zip_code', $columns)) {
                    $table->string('zip_code', 14)->default('')
                        ->index('zip_code_i');
                }
                if (!in_array('country', $columns)) {
                    $table->string('country', 24)->nullable();
                }
                if (!in_array('address_name', $columns)) {
                    $table->string('address_name', 100)->default('')
                        ->index('address_name');
                }
                if (!in_array('person_id', $columns)) {
                    $table->integer('person_id')->nullable()->default(0)
                        ->index('person_id');
                }
                if (!in_array('type_id', $columns)) {
                    $table->integer('type_id')->unsigned()->default(0);
                }
                if (!in_array('is_default', $columns)) {
                    $table->boolean('is_default')->nullable()->default(0);
                }
                if (!in_array('is_residential', $columns)) {
                    $table->boolean('is_residential')->default(0);
                }
                if (!in_array('date_created', $columns)) {
                    $table->dateTime('date_created')->nullable()
                        ->nullable();
                }
                if (!in_array('date_modified', $columns)) {
                    $table->dateTime('date_modified')->nullable()
                        ->nullable();
                }
                if (!in_array('latitude', $columns)) {
                    $table->string('latitude', 15)->nullable();
                }
                if (!in_array('longitude', $columns)) {
                    $table->string('longitude', 15)->nullable();
                }
                if (!in_array('coords_accuracy', $columns)) {
                    $table->string('coords_accuracy', 4)->nullable();
                }
                if (!in_array('geocoded', $columns)) {
                    $table->boolean('geocoded')->default(0);
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
