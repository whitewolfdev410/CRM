<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLinkPersonWoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('link_person_wo')) {
            Schema::create('link_person_wo', function (Blueprint $table) {
                $table->increments('link_person_wo_id');
            });
        }

        $columns = Schema::getColumnListing('link_person_wo');

        Schema::table(
            'link_person_wo',
            function (Blueprint $table) use ($columns) {
                if (!in_array('link_person_wo_id', $columns)) {
                    $table->increments('link_person_wo_id');
                }
                if (!in_array('work_order_id', $columns)) {
                    $table->integer('work_order_id')->unsigned()->index('work_order_id_i');
                }
                if (!in_array('person_id', $columns)) {
                    $table->integer('person_id')->unsigned()->index('person_id_i');
                }
                if (!in_array('creator_person_id', $columns)) {
                    $table->integer('creator_person_id')->nullable()->default(0);
                }
                if (!in_array('bill_final', $columns)) {
                    $table->boolean('bill_final')->default(0);
                }
                if (!in_array('bill_number', $columns)) {
                    $table->string('bill_number', 30);
                }
                if (!in_array('bill_amount', $columns)) {
                    $table->decimal('bill_amount');
                }
                if (!in_array('bill_date', $columns)) {
                    $table->date('bill_date');
                }
                if (!in_array('bill_description', $columns)) {
                    $table->string('bill_description');
                }
                if (!in_array('vendor_notes', $columns)) {
                    $table->text('vendor_notes', 65535);
                }
                if (!in_array('qb_ref', $columns)) {
                    $table->string('qb_ref', 32)->index('qb_ref');
                }
                if (!in_array('qb_transfer_date', $columns)) {
                    $table->dateTime('qb_transfer_date');
                }
                if (!in_array('qb_info', $columns)) {
                    $table->text('qb_info', 65535);
                }
                if (!in_array('confirmed_date', $columns)) {
                    $table->dateTime('confirmed_date')->nullable()->index('confirmed_date');
                }
                if (!in_array('created_date', $columns)) {
                    $table->dateTime('created_date')->nullable()->index('created_date');
                }
                if (!in_array('modified_date', $columns)) {
                    $table->dateTime('modified_date')->nullable();
                }
                if (!in_array('status', $columns)) {
                    $table->boolean('status')->nullable();
                }
                if (!in_array('is_disabled', $columns)) {
                    $table->boolean('is_disabled')->nullable()->default(0)->index('is_disabled_i');
                }
                if (!in_array('disabling_person_id', $columns)) {
                    $table->integer('disabling_person_id')->nullable();
                }
                if (!in_array('disabled_date', $columns)) {
                    $table->dateTime('disabled_date')->nullable();
                }
                if (!in_array('cancel_reason_type_id', $columns)) {
                    $table->integer('cancel_reason_type_id')->unsigned()->index('cancel_reason_type_id');
                }
                if (!in_array('status_type_id', $columns)) {
                    $table->integer('status_type_id')->unsigned()->nullable()->index('status_type_id');
                }
                if (!in_array('type', $columns)) {
                    $table->enum(
                        'type',
                        ['quote', 'recall', 'work']
                    )->nullable();
                }
                if (!in_array('recall_link_person_wo_id', $columns)) {
                    $table->integer('recall_link_person_wo_id')->unsigned()->default(0)->index('recall_link_person_wo_id');
                }
                if (!in_array('is_hidden', $columns)) {
                    $table->boolean('is_hidden')->nullable()->default(0)->index('is_hidden');
                }
                if (!in_array('priority', $columns)) {
                    $table->integer('priority')->nullable()->default(1)->index('priority');
                }
                if (!in_array('special_type', $columns)) {
                    $table->enum(
                        'special_type',
                        ['none', '2hr_min']
                    )->default('none');
                }
                if (!in_array('estimated_time', $columns)) {
                    $table->time('estimated_time')->nullable();
                }
                if (!in_array('send_past_due_notice', $columns)) {
                    $table->boolean('send_past_due_notice')->default(0);
                }
                if (!in_array('last_past_due_notice_number', $columns)) {
                    $table->boolean('last_past_due_notice_number')->default(0);
                }
                if (!in_array('person_type', $columns)) {
                    $table->integer('person_type')->unsigned()->default(1);
                }
                if (!in_array('person_permission', $columns)) {
                    $table->integer('person_permission')->unsigned()->nullable();
                }
                if (!in_array('sleep_due_date', $columns)) {
                    $table->date('sleep_due_date')->nullable();
                }
                if (!in_array('sleep_reason', $columns)) {
                    $table->text('sleep_reason', 65535)->nullable();
                }
                if (!in_array('reference_number', $columns)) {
                    $table->string('reference_number', 20)->nullable();
                }
                if (!in_array('confirmed_date', $columns)) {
                    $table->index(
                        ['confirmed_date', 'created_date'],
                        'confirmed_create_date'
                    );
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
