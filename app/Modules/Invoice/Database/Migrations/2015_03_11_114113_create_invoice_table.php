<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInvoiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('invoice')) {
            Schema::create('invoice', function (Blueprint $table) {
                $table->bigInteger('invoice_id', true)->unsigned();
            });
        }

        $columns = Schema::getColumnListing('invoice');

        Schema::table(
            'invoice',
            function (Blueprint $table) use ($columns) {
                if (!in_array('invoice_id', $columns)) {
                    $table->bigInteger('invoice_id', true)->unsigned();
                }
                if (!in_array('invoice_number', $columns)) {
                    $table->string('invoice_number', 25)->nullable()
                        ->index('invoice_number_i');
                }
                if (!in_array('person_id', $columns)) {
                    $table->integer('person_id')->unsigned()->default(0)
                        ->index('person_id');
                }
                if (!in_array('date_invoice', $columns)) {
                    $table->date('date_invoice')->nullable()
                        ->index('date_invoice_i');
                }
                if (!in_array('date_due', $columns)) {
                    $table->date('date_due')->nullable();
                }
                if (!in_array('statement_id', $columns)) {
                    $table->integer('statement_id')->unsigned()->nullable();
                }
                if (!in_array('paid', $columns)) {
                    $table->char('paid', 1)->nullable()->default(0)
                        ->index('paid');
                }
                if (!in_array('creator_person_id', $columns)) {
                    $table->integer('creator_person_id')->unsigned()
                        ->nullable();
                }
                if (!in_array('date_created', $columns)) {
                    $table->dateTime('date_created')->nullable()
                        ->index('date_created_i');
                }
                if (!in_array('date_modified', $columns)) {
                    $table->dateTime('date_modified')->nullable()
                        ->index('date_modified_i');
                }
                if (!in_array('work_order_id', $columns)) {
                    $table->integer('work_order_id')->unsigned()->nullable()
                        ->index('work_order_id_i');
                }
                if (!in_array('table_name', $columns)) {
                    $table->string('table_name', 50)->nullable();
                }
                if (!in_array('table_id', $columns)) {
                    $table->integer('table_id')->unsigned()->nullable();
                }
                if (!in_array('customer_request_description', $columns)) {
                    $table->text('customer_request_description')->nullable();
                }
                if (!in_array('job_description', $columns)) {
                    $table->text('job_description')->nullable();
                }
                if (!in_array('ship_address_id', $columns)) {
                    $table->integer('ship_address_id')->unsigned()->default(0);
                }
                if (!in_array('currency', $columns)) {
                    $table->string('currency', 3)->default('USD');
                }
                if (!in_array('billing_person_name', $columns)) {
                    $table->string('billing_person_name', 64)->nullable();
                }
                if (!in_array('billing_address_line1', $columns)) {
                    $table->string('billing_address_line1', 48)->nullable();
                }
                if (!in_array('billing_address_line2', $columns)) {
                    $table->string('billing_address_line2', 48)->nullable();
                }
                if (!in_array('billing_address_city', $columns)) {
                    $table->string('billing_address_city', 48)->nullable();
                }
                if (!in_array('billing_address_state', $columns)) {
                    $table->string('billing_address_state', 24)->nullable();
                }
                if (!in_array('billing_address_zip_code', $columns)) {
                    $table->string('billing_address_zip_code', 14)->nullable();
                }
                if (!in_array('billing_address_country', $columns)) {
                    $table->string('billing_address_country', 24)->nullable();
                }
                if (!in_array('shipping_person_name', $columns)) {
                    $table->string('shipping_person_name', 64)->nullable();
                }
                if (!in_array('shipping_address_line1', $columns)) {
                    $table->string('shipping_address_line1', 48)->nullable();
                }
                if (!in_array('shipping_address_line2', $columns)) {
                    $table->string('shipping_address_line2', 48)->nullable();
                }
                if (!in_array('shipping_address_city', $columns)) {
                    $table->string('shipping_address_city', 48)->nullable();
                }
                if (!in_array('shipping_address_state', $columns)) {
                    $table->string('shipping_address_state', 24)->nullable();
                }
                if (!in_array('shipping_address_zip_code', $columns)) {
                    $table->string('shipping_address_zip_code', 14)->nullable();
                }
                if (!in_array('shipping_address_country', $columns)) {
                    $table->string('shipping_address_country', 24)->nullable();
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
