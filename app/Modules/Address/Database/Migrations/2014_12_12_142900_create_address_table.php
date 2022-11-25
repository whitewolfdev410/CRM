<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAddressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('address')) {
            Schema::create('address', function (Blueprint $table) {
                $table->increments('address_id');
                $table->string('address_1', 48)->default('0');
                $table->string('address_2', 48)->nullable();
                $table->string('city', 48)->default('0')->index('city_i');
                $table->string('county', 28)->default('');
                $table->string('state', 24)->nullable()->index('state_index');
                $table->string('zip_code', 14)->default('')
                    ->index('zip_code_i');
                $table->string('country', 24)->nullable();
                $table->string('address_name', 100)->default('')
                    ->index('address_name');
                $table->integer('person_id')->nullable()->default(0)
                    ->index('person_id');
                $table->integer('type_id')->unsigned()->default(0);
                $table->boolean('is_default')->nullable()->default(0);
                $table->boolean('is_residential')->default(0);
                $table->dateTime('date_created')->nullable()
                    ->nullable();
                $table->dateTime('date_modified')->nullable()
                    ->nullable();
                $table->string('latitude', 15)->nullable();
                $table->string('longitude', 15)->nullable();
                $table->string('coords_accuracy', 4)->nullable();
                $table->boolean('geocoded')->default(0);
                $table->index(['address_1', 'address_2'], 'address_1_2_i');
                $table->index(
                    ['address_1', 'address_2', 'zip_code', 'city'],
                    'ygh'
                );
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
