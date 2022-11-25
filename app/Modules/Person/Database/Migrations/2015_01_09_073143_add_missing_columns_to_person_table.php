<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMissingColumnsToPersonTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('person')) {
            Schema::table('person', function (Blueprint $table) {
                if (!Schema::hasColumn('person', 'custom_1')) {
                    $table->string('custom_1', 128)->nullable()->default('0')
                        ->index('custom_1');
                }
                if (!Schema::hasColumn('person', 'custom_2')) {
                    $table->string('custom_2', 128)->nullable()->default('');
                }

                if (!Schema::hasColumn('person', 'custom_3')) {
                    $table->string('custom_3', 128)->nullable()->default('')
                        ->index('custom_3');
                }

                if (!Schema::hasColumn('person', 'custom_4')) {
                    $table->string('custom_4', 128)->nullable()->default('');
                }
                if (!Schema::hasColumn('person', 'custom_5')) {
                    $table->string('custom_5', 128)->nullable()->default('')
                        ->index('custom_5_i');
                }
                if (!Schema::hasColumn('person', 'custom_6')) {
                    $table->string('custom_6', 128)->nullable()->default('')
                        ->index('custom_6_i');
                }
                if (!Schema::hasColumn('person', 'custom_7')) {
                    $table->string('custom_7', 128)->nullable()->default('')
                        ->index('custom_7_i');
                }
                if (!Schema::hasColumn('person', 'custom_8')) {
                    $table->string('custom_8', 128)->nullable()->default('');
                }

                if (!Schema::hasColumn('person', 'custom_9')) {
                    $table->string('custom_9', 128)->nullable()->default('');
                }

                if (!Schema::hasColumn('person', 'custom_10')) {
                    $table->string('custom_10', 128)->nullable();
                }

                if (!Schema::hasColumn('person', 'custom_11')) {
                    $table->string('custom_11', 128)->nullable()
                        ->index('custom_11_i');
                }

                if (!Schema::hasColumn('person', 'custom_12')) {
                    $table->string('custom_12', 128)->nullable();
                }

                if (!Schema::hasColumn('person', 'custom_13')) {
                    $table->string('custom_13', 128)->nullable();
                }

                if (!Schema::hasColumn('person', 'custom_14')) {
                    $table->string('custom_14', 128)->nullable();
                }

                if (!Schema::hasColumn('person', 'custom_15')) {
                    $table->string('custom_15', 128)->nullable();
                }

                if (!Schema::hasColumn('person', 'custom_16')) {
                    $table->string('custom_16', 128)->nullable();
                }

                if (!Schema::hasColumn('person', 'sex')) {
                    $table->enum('sex', ['m', 'f'])->nullable()->default('m');
                }

                if (!Schema::hasColumn('person', 'dob')) {
                    $table->date('dob')->nullable();
                }

                if (!Schema::hasColumn('person', 'login')) {
                    $table->string('login', 128)->nullable()->default('');
                }

                if (!Schema::hasColumn('person', 'password')) {
                    $table->string('password', 128)->nullable();
                }

                if (!Schema::hasColumn('person', 'email')) {
                    $table->string('email', 128)->nullable();
                }

                if (!Schema::hasColumn('person', 'pricing_structure_id')) {
                    $table->integer('pricing_structure_id')->nullable()->default(9);
                }

                if (!Schema::hasColumn('person', 'payment_terms_id')) {
                    $table->integer('payment_terms_id')->unsigned()->nullable()
                        ->default(0);
                }
                if (!Schema::hasColumn('person', 'assigned_to_person_id')) {
                    $table->integer('assigned_to_person_id')->nullable()
                        ->default(0);
                }
                if (!Schema::hasColumn('person', 'perm_group_id')) {
                    $table->integer('perm_group_id')->nullable()->default(0)
                        ->index('perm_group_id');
                }

                if (!Schema::hasColumn('person', 'type_id')) {
                    $table->integer('type_id')->default(0)->index('type_id');
                }

                if (!Schema::hasColumn('person', 'status_type_id')) {
                    $table->integer('status_type_id')->unsigned()->nullable()
                        ->default(191);
                }
                if (!Schema::hasColumn('person', 'referral_person_id')) {
                    $table->integer('referral_person_id')->unsigned()->nullable();
                }

                if (!Schema::hasColumn('person', 'kind')) {
                    $table->string('kind', 24)->default('person')->index('kind');
                }

                if (!Schema::hasColumn('person', 'date_created')) {
                    $table->dateTime('date_created')->nullable()
                        ->nullable();
                }
                if (!Schema::hasColumn('person', 'date_modified')) {
                    $table->dateTime('date_modified')->nullable()
                        ->nullable();
                }
                if (!Schema::hasColumn('person', 'notes')) {
                    $table->text('notes')->nullable();
                }

                if (!Schema::hasColumn('person', 'last_ip')) {
                    $table->string('last_ip', 15)->default('0.0.0.0');
                }

                if (!Schema::hasColumn('person', 'total_balance')) {
                    $table->decimal('total_balance', 8)->nullable();
                }

                if (!Schema::hasColumn('person', 'total_invoiced')) {
                    $table->decimal('total_invoiced', 8)->nullable();
                }

                if (!Schema::hasColumn('person', 'token')) {
                    $table->string('token', 64)->nullable();
                }

                if (!Schema::hasColumn('person', 'token_time')) {
                    $table->dateTime('token_time');
                }

                if (!Schema::hasColumn('person', 'owner_person_id')) {
                    $table->integer('owner_person_id')->unsigned()->default(0);
                }

                // new columns comparing to base table

                if (!Schema::hasColumn('person', 'salutation')) {
                    $table->string('salutation', 16)->nullable();
                }

                if (!Schema::hasColumn('person', 'industry_type_id')) {
                    $table->integer('industry_type_id')->unsigned()->default(0)
                        ->index('industry_type_id');
                }

                if (!Schema::hasColumn('person', 'rot_type_id')) {
                    $table->integer('rot_type_id')->unsigned()->default(0)
                        ->index('rot_type_id');
                }

                if (!Schema::hasColumn('person', 'commission')) {
                    $table->tinyInteger('commission', false, true)->default(0);
                }

                if (!Schema::hasColumn('person', 'total_due_today')) {
                    $table->decimal('total_due_today', 8)->default(0);
                }

                if (!Schema::hasColumn('person', 'suspend_invoice')) {
                    $table->tinyInteger('suspend_invoice', false, true)->default(0)
                        ->index('suspend_invoice');
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
