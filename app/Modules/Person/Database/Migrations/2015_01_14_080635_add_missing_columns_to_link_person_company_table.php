<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMissingColumnsToLinkPersonCompanyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('link_person_company')) {
            Schema::table('link_person_company', function (Blueprint $table) {
                if (!Schema::hasColumn(
                    'link_person_company',
                    'link_person_company_id'
                )
                ) {
                    $table->integer('link_person_company_id', true);
                }

                if (!Schema::hasColumn('link_person_company', 'person_id')) {
                    $table->integer('person_id')->default(0)->index('person_id');
                }

                if (!Schema::hasColumn('link_person_company', 'member_person_id')) {
                    $table->integer('member_person_id')->default(0)
                        ->index('member_person_id');
                }

                if (!Schema::hasColumn('link_person_company', 'address_id')) {
                    $table->integer('address_id')->unsigned()->default(0)
                        ->index('addres_id');
                }

                if (!Schema::hasColumn('link_person_company', 'address_id2')) {
                    $table->integer('address_id2')->after('address_id')->unsigned()
                        ->default(0)
                        ->index('address_id2');
                }

                if (!Schema::hasColumn('link_person_company', 'position')) {
                    $table->string('position', 48)->nullable()
                        ->default('no position');
                }

                if (!Schema::hasColumn('link_person_company', 'position2')) {
                    $table->string('position2', 48)->after('position')->nullable()
                        ->default('no position');
                }

                if (!Schema::hasColumn('link_person_company', 'start_date')) {
                    $table->date('start_date')->nullable();
                }

                if (!Schema::hasColumn('link_person_company', 'end_date')) {
                    $table->date('end_date')->nullable();
                }

                if (!Schema::hasColumn('link_person_company', 'type_id')) {
                    $table->integer('type_id')->unsigned()->nullable();
                }

                if (!Schema::hasColumn('link_person_company', 'type_id2')) {
                    $table->integer('type_id2')->after('type_id')->unsigned()
                        ->nullable();
                }

                if (!Schema::hasColumn('link_person_company', 'is_default')) {
                    $table->tinyInteger('is_default', false, true)->nullable()
                        ->default(0);
                }

                if (!Schema::hasColumn('link_person_company', 'is_default2')) {
                    $table->tinyInteger('is_default2', false, true)
                        ->after('is_default')->nullable()
                        ->default(0);
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
