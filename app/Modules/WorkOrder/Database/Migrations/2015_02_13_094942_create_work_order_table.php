<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWorkOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('work_order')) {
            Schema::create('work_order', function (Blueprint $table) {
                $table->increments('work_order_id');
            });
        }

        $columns = Schema::getColumnListing('work_order');

        Schema::table(
            'work_order',
            function (Blueprint $table) use ($columns) {
                if (!in_array('work_order_id', $columns)) {
                    $table->increments('work_order_id');
                }
                if (!in_array('work_order_number', $columns)) {
                    $table->string('work_order_number', 24)->nullable()
                        ->index('work_order_number');
                }
                if (!in_array('company_person_id', $columns)) {
                    $table->integer('company_person_id')->unsigned()->nullable()
                        ->index('company_person_id');
                }
                if (!in_array('description', $columns)) {
                    $table->text('description')->nullable();
                }
                if (!in_array('received_date', $columns)) {
                    $table->dateTime('received_date')->nullable()
                        ->index('received_date_i');
                }
                if (!in_array('acknowledged_person_id', $columns)) {
                    $table->integer('acknowledged_person_id')->unsigned()
                        ->nullable()->default(0);
                }
                if (!in_array('expected_completion_date', $columns)) {
                    $table->dateTime('expected_completion_date')->nullable();
                }
                if (!in_array('dispatched_to_person_id', $columns)) {
                    $table->integer('dispatched_to_person_id')->unsigned()
                        ->nullable();
                }
                if (!in_array('actual_completion_date', $columns)) {
                    $table->dateTime('actual_completion_date')->nullable();
                }
                if (!in_array('completion_code', $columns)) {
                    $table->string('completion_code', 24)->nullable();
                }
                if (!in_array('estimated_time', $columns)) {
                    $table->integer('estimated_time')->unsigned()->default(0);
                }
                if (!in_array('created_date', $columns)) {
                    $table->dateTime('created_date')->nullable()
                        ->index('created_date');
                }
                if (!in_array('modified_date', $columns)) {
                    $table->dateTime('modified_date')->nullable();
                }
                if (!in_array('tracking_number', $columns)) {
                    $table->integer('tracking_number')->unsigned();
                }
                if (!in_array('attention', $columns)) {
                    $table->string('attention');
                }
                if (!in_array('trade', $columns)) {
                    $table->string('trade');
                }
                if (!in_array('trade_type_id', $columns)) {
                    $table->integer('trade_type_id')->unsigned()->nullable();
                }
                if (!in_array('request', $columns)) {
                    $table->string('request');
                }
                if (!in_array('not_to_exceed', $columns)) {
                    $table->string('not_to_exceed', 10);
                }
                if (!in_array('requested_completion_date', $columns)) {
                    $table->date('requested_completion_date');
                }
                if (!in_array('instructions', $columns)) {
                    $table->text('instructions');
                }
                if (!in_array('fax_sender', $columns)) {
                    $table->string('fax_sender', 100);
                }
                if (!in_array('fax_recipient', $columns)) {
                    $table->string('fax_recipient', 100);
                }
                if (!in_array('fax_pages', $columns)) {
                    $table->boolean('fax_pages')->default(0);
                }
                if (!in_array('requested_by', $columns)) {
                    $table->string('requested_by', 100);
                }
                if (!in_array('phone', $columns)) {
                    $table->string('phone', 100);
                }
                if (!in_array('email', $columns)) {
                    $table->string('email');
                }
                if (!in_array('requested_date', $columns)) {
                    $table->date('requested_date')->nullable();
                }
                if (!in_array('required_date', $columns)) {
                    $table->date('required_date')->nullable();
                }
                if (!in_array('priority', $columns)) {
                    $table->string('priority', 30);
                }
                if (!in_array('crm_priority_type_id', $columns)) {
                    $table->integer('crm_priority_type_id')->unsigned()
                        ->index('crm_priority_type_id');
                }
                if (!in_array('category', $columns)) {
                    $table->string('category', 100);
                }
                if (!in_array('type', $columns)) {
                    $table->string('type', 100);
                }
                if (!in_array('fin_loc', $columns)) {
                    $table->string('fin_loc');
                }
                if (!in_array('store_hours', $columns)) {
                    $table->string('store_hours', 40);
                }
                if (!in_array('shop', $columns)) {
                    $table->string('shop', 40);
                }
                if (!in_array('fac_supv', $columns)) {
                    $table->string('fac_supv');
                }
                if (!in_array('wo_status_type_id', $columns)) {
                    $table->integer('wo_status_type_id')->unsigned()
                        ->default(542)->index('wo_status_type_id_i');
                }
                if (!in_array('cancel_reason_type_id', $columns)) {
                    $table->integer('cancel_reason_type_id')->unsigned()
                        ->index('cancel_reason_type_id');
                }
                if (!in_array('via_type_id', $columns)) {
                    $table->integer('via_type_id')->unsigned()
                        ->index('via_type_id');
                }
                if (!in_array('extended_date', $columns)) {
                    $table->dateTime('extended_date')->nullable();
                }
                if (!in_array('extended_why', $columns)) {
                    $table->text('extended_why');
                }
                if (!in_array('invoice_id', $columns)) {
                    $table->bigInteger('invoice_id')->unsigned()->default(0);
                }
                if (!in_array('invoice_amount', $columns)) {
                    $table->decimal('invoice_amount', 7)
                        ->index('invoice_amount');
                }
                if (!in_array('costs', $columns)) {
                    $table->decimal('costs', 7)->index('costs');
                }
                if (!in_array('locked_id', $columns)) {
                    $table->integer('locked_id')->unsigned()->default(0);
                }
                if (!in_array('pickup_id', $columns)) {
                    $table->integer('pickup_id')->unsigned()->default(0);
                }
                if (!in_array('shop_address_id', $columns)) {
                    $table->integer('shop_address_id')->unsigned()
                        ->index('shop_address_id');
                }
                if (!in_array('acknowledged', $columns)) {
                    $table->boolean('acknowledged')->nullable()->default(0);
                }
                if (!in_array('invoice_status_type_id', $columns)) {
                    $table->integer('invoice_status_type_id')->unsigned()
                        ->default(539)->index('invoice_status');
                }
                if (!in_array('bill_status_type_id', $columns)) {
                    $table->integer('bill_status_type_id')->unsigned()
                        ->default(585)->index('bill_status');
                }
                if (!in_array('creator_person_id', $columns)) {
                    $table->integer('creator_person_id')->default(0)
                        ->index('creator_person_id');
                }
                if (!in_array('quote_status_type_id', $columns)) {
                    $table->integer('quote_status_type_id')->unsigned()
                        ->nullable();
                }
                if (!in_array('invoice_number', $columns)) {
                    $table->string('invoice_number', 30)->nullable();
                }
                if (!in_array('project_manager_person_id', $columns)) {
                    $table->integer('project_manager_person_id')->unsigned()
                        ->nullable();
                }
                if (!in_array('scheduled_date', $columns)) {
                    $table->dateTime('scheduled_date')->nullable();
                }
                if (!in_array('authorization_code', $columns)) {
                    $table->string('authorization_code', 24)->nullable();
                }
                if (!in_array('requested_by_person_id', $columns)) {
                    $table->integer('requested_by_person_id')->unsigned()
                        ->nullable();
                }
                if (!in_array('billing_company_person_id', $columns)) {
                    $table->integer('billing_company_person_id')->unsigned()
                        ->nullable();
                }
                if (!in_array('customer_setting_id', $columns)) {
                    $table->integer('customer_setting_id')->default(0);
                }
                if (!in_array('client_status', $columns)) {
                    $table->text('client_status');
                }
                if (!in_array('work_order_number', $columns)) {
                    $table->index(
                        ['work_order_number', 'received_date'],
                        'wo_number_received_i'
                    );
                }
                if (!in_array('actual_completion_date', $columns)) {
                    $table->index(
                        ['actual_completion_date', 'created_date'],
                        'actual_completion_date'
                    );
                }
                if (!in_array('parts_status_type_id', $columns)) {
                    $table->mediumInteger('parts_status_type_id')->unsigned()
                        ->nullable()->default(null);
                }
                if (!in_array('supplier_person_id', $columns)) {
                    $table->mediumInteger('supplier_person_id')->unsigned()
                        ->nullable()->default(null);
                }
                if (!in_array('purchase_order', $columns)) {
                    $table->string('purchase_order', 255)->nullable()
                        ->default(null);
                }
                if (!in_array('work_performed', $columns)) {
                    $table->text('work_performed')->nullable()->default(null);
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
