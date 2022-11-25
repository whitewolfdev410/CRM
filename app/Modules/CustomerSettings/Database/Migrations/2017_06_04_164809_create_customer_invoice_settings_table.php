<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomerInvoiceSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('customer_invoice_settings')) {
            Schema::create('customer_invoice_settings', function (Blueprint $table) {
                $table->increments('customer_invoice_settings_id');
            });
        }

        $columns = Schema::getColumnListing('customer_invoice_settings');

        Schema::table(
            'customer_invoice_settings',
            function (Blueprint $table) use ($columns) {
                if (!in_array('customer_invoice_settings_id', $columns)) {
                    $table->increments('customer_invoice_settings_id');
                }
                if (!in_array('company_person_id', $columns)) {
                    $table->integer('company_person_id')->unsigned()
                        ->index('company_person_id');
                }
                if (!in_array('delivery_method', $columns)) {
                    $table->enum('delivery_method', ['mail', 'email'])->nullable();
                }
                if (!in_array('active', $columns)) {
                    $table->boolean('active')->default(0);
                }
                if (!in_array('options', $columns)) {
                    $table->json('options')->nullable();
                }
                if (!in_array('created_date', $columns)) {
                    $table->timestamp('created_date')->nullable();
                }
                if (!in_array('updated_at', $columns)) {
                    $table->timestamp('updated_at')->nullable();
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
