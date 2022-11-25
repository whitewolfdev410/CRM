<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePersonTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('person')) {
            Schema::create('person', function (Blueprint $table) {
                $table->increments('person_id');
                $table->string('custom_1', 128)->nullable()->default('0')
                    ->index('custom_1');
                $table->string('custom_2', 128)->nullable()->default('');
                $table->string('custom_3', 128)->nullable()->default('')
                    ->index('custom_3');
                $table->string('custom_4', 128)->nullable()->default('');
                $table->string('custom_5', 128)->nullable()->default('')
                    ->index('custom_5_i');
                $table->string('custom_6', 128)->nullable()->default('')
                    ->index('custom_6_i');
                $table->string('custom_7', 128)->nullable()->default('')
                    ->index('custom_7_i');
                $table->string('custom_8', 128)->nullable()->default('');
                $table->string('custom_9', 128)->nullable()->default('');
                $table->string('custom_10', 128)->nullable();
                $table->string('custom_11', 128)->nullable()
                    ->index('custom_11_i');
                $table->string('custom_12', 128)->nullable();
                $table->string('custom_13', 128)->nullable();
                $table->string('custom_14', 128)->nullable();
                $table->string('custom_15', 128)->nullable();
                $table->string('custom_16', 128)->nullable();
                $table->enum('sex', ['m', 'f'])->nullable()->default('m');
                $table->date('dob')->nullable();
                $table->string('login', 128)->nullable()->default('');
                $table->string('password', 128)->nullable();
                $table->string('email', 128)->nullable();
                $table->integer('pricing_structure_id')->nullable()->default(9);
                $table->integer('payment_terms_id')->unsigned()->nullable()
                    ->default(0);
                $table->integer('assigned_to_person_id')->nullable()
                    ->default(0);
                $table->integer('perm_group_id')->nullable()->default(0)
                    ->index('perm_group_id');
                $table->integer('type_id')->default(0)->index('type_id');
                $table->integer('status_type_id')->unsigned()->nullable()
                    ->default(191);
                $table->integer('referral_person_id')->unsigned()->nullable();
                $table->string('kind', 24)->default('person')->index('kind');
                $table->dateTime('date_created')->nullable()
                    ->nullable();
                $table->dateTime('date_modified')->nullable()
                    ->nullable();
                $table->text('notes')->nullable();
                $table->string('last_ip', 15)->default('0.0.0.0');
                $table->decimal('total_balance', 8)->nullable();
                $table->decimal('total_invoiced', 8)->nullable();
                $table->string('token', 64)->nullable();
                $table->dateTime('token_time');
                $table->integer('owner_person_id')->unsigned()->default(0);
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
