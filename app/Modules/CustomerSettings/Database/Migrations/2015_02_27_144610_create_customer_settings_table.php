<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomerSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('customer_settings')) {
            Schema::create('customer_settings', function (Blueprint $table) {
                $table->increments('customer_settings_id');
            });
        }

        $columns = Schema::getColumnListing('customer_settings');

        Schema::table(
            'customer_settings',
            function (Blueprint $table) use ($columns) {
                if (!in_array('customer_settings_id', $columns)) {
                    $table->increments('customer_settings_id');
                }
                if (!in_array('company_person_id', $columns)) {
                    $table->integer('company_person_id')->unsigned()
                        ->index('company_person_id');
                }

                if (!in_array('required_completion_code', $columns)) {
                    $table->boolean('required_completion_code')->default(0);
                }
                if (!in_array('completion_code_format', $columns)) {
                    $table->string('completion_code_format', 64)->nullable();
                }
                if (!in_array('required_work_order_signature', $columns)) {
                    $table->boolean('required_work_order_signature')
                        ->default(1);
                }
                if (!in_array('ivr_number', $columns)) {
                    $table->string('ivr_number', 32)->nullable();
                }
                if (!in_array('footer_file_id', $columns)) {
                    $table->integer('footer_file_id')->unsigned()->nullable();
                }
                if (!in_array('footer_text', $columns)) {
                    $table->text('footer_text')->nullable();
                }
                if (!in_array('uses_authorization_code', $columns)) {
                    $table->boolean('uses_authorization_code')->default(0);
                }
                if (!in_array('auto_generate_work_order_number', $columns)) {
                    $table->boolean('auto_generate_work_order_number')
                        ->default(0);
                }
                if (!in_array('accept_work_order_invitation', $columns)) {
                    $table->boolean('accept_work_order_invitation')->nullable();
                }
                if (!in_array('meta_data', $columns)) {
                    $table->binary('meta_data')->nullable()->default(null);
                }

                if (!in_array('created_date', $columns)) {
                    $table->timestamp('created_date')->nullable();
                }
                if (!in_array('updated_at', $columns)) {
                    $table->timestamp('updated_at');
                }
            }
        );
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
