<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateInvoiceEntryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('invoice_entry')) {
            Schema::create('invoice_entry', function (Blueprint $table) {
                $table->bigInteger('invoice_entry_id', true)->unsigned();
            });
        }

        $columns = Schema::getColumnListing('invoice_entry');

        $longEntryAdded = false;

        Schema::table(
            'invoice_entry',
            function (Blueprint $table) use ($columns, &$longEntryAdded) {
                if (!in_array('invoice_entry_id', $columns)) {
                    $table->bigInteger('invoice_entry_id', true)->unsigned();
                }
                if (!in_array('entry_short', $columns)) {
                    $table->string('entry_short', 128)->nullable()
                        ->index('entry_short_i');
                }
                if (!in_array('entry_long', $columns)) {
                    $table->text('entry_long')->nullable();
                    $longEntryAdded = true;
                }
                if (!in_array('qty', $columns)) {
                    $table->float('qty', 6)->nullable()->default(0.00);
                }
                if (!in_array('price', $columns)) {
                    $table->decimal('price', 10)->nullable()->default(0.00);
                }
                if (!in_array('total', $columns)) {
                    $table->decimal('total', 10)->nullable()->default(0.00);
                }
                if (!in_array('unit', $columns)) {
                    $table->string('unit', 100)->nullable();
                }
                if (!in_array('entry_date', $columns)) {
                    $table->date('entry_date')->nullable();
                }
                if (!in_array('service_id', $columns)) {
                    $table->integer('service_id')->nullable()->default(0);
                }
                if (!in_array('service_id2', $columns)) {
                    $table->integer('service_id2')->unsigned()->nullable()
                        ->default(0);
                }
                if (!in_array('item_id', $columns)) {
                    $table->integer('item_id')->unsigned()->nullable()
                        ->default(0)->index('item_id_i');
                }
                if (!in_array('person_id', $columns)) {
                    $table->integer('person_id')->nullable()->default(0)
                        ->index('person_id');
                }
                if (!in_array('invoice_id', $columns)) {
                    $table->bigInteger('invoice_id')->unsigned()->nullable()
                        ->default(0)->index('invoice_id_i');
                }
                if (!in_array('order_id', $columns)) {
                    $table->integer('order_id')->unsigned()->nullable()
                        ->default(0);
                }
                if (!in_array('calendar_event_id', $columns)) {
                    $table->integer('calendar_event_id')->nullable()
                        ->default(0);
                }
                if (!in_array('is_disabled', $columns)) {
                    $table->boolean('is_disabled')->nullable()->default(0);
                }
                if (!in_array('func', $columns)) {
                    $table->string('func', 128)->nullable()->default('');
                }
                if (!in_array('tax_rate', $columns)) {
                    $table->float('tax_rate', 7)->nullable()->default(0.00);
                }
                if (!in_array('tax_amount', $columns)) {
                    $table->float('tax_amount', 7)->nullable()->default(0.00);
                }
                if (!in_array('discount', $columns)) {
                    $table->decimal('discount', 8, 4)->nullable();
                }
                if (!in_array('packaged', $columns)) {
                    $table->boolean('packaged')->nullable();
                }
                if (!in_array('creator_person_id', $columns)) {
                    $table->integer('creator_person_id')->unsigned()
                        ->nullable();
                }
                if (!in_array('register_id', $columns)) {
                    $table->integer('register_id')->unsigned()->default(0);
                }
                if (!in_array('created_date', $columns)) {
                    $table->dateTime('created_date')->nullable()
                        ->nullable();
                }
                if (!in_array('currency', $columns)) {
                    $table->string('currency', 3)->default('USD');
                }

                if (!in_array('sort_order', $columns)) {
                    $table->smallInteger('sort_order')->nullable()->default(0);
                }

                if (!in_array('updated_at', $columns)) {
                    $table->timestamp('updated_at');
                }
            }
        );

        if ($longEntryAdded) {
            DB::statement('CREATE INDEX entry_long_i ON invoice_entry (entry_long(200));');
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
