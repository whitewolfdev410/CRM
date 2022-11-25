<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLinkPersonCompanyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('link_person_company')) {
            Schema::create('link_person_company', function (Blueprint $table) {
                $table->integer('link_person_company_id', true);
                $table->integer('person_id')->default(0)->index('person_id');
                $table->integer('member_person_id')->default(0)
                    ->index('member_person_id');
                $table->integer('address_id')->unsigned()->default(0)
                    ->index('addres_id');
                $table->string('position', 48)->nullable()
                    ->default('no position');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->integer('type_id')->unsigned()->nullable();
                $table->tinyInteger(
                    'is_default',
                    false,
                    true
                )->nullable()->default(0);
            });
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
