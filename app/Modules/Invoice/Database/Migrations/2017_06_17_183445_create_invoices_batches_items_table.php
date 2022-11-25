<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInvoicesBatchesItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'invoice_batch_item_id';

        if (!Schema::hasTable('invoice_batch_item')) {
            Schema::create(
                'invoice_batch_item',
                function ($table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );
        }

        $columns = Schema::getColumnListing('invoice_batch_item');

        Schema::table(
            'invoice_batch_item',
            function ($table) use ($columns, $primaryKeyName) {
                if (!in_array($primaryKeyName, $columns)) {
                    $table->increments($primaryKeyName);
                }
                if (!in_array('invoice_batch_id', $columns)) {
                    $table->integer('invoice_batch_id')
                        ->unsigned()
                        ->index();
                }
                if (!in_array('invoice_id', $columns)) {
                    $table->integer('invoice_id')
                        ->unsigned()
                        ->index();
                }
                if (!in_array('created_at', $columns)) {
                    $table->timestamp('created_at');
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
        /* we need to assume everything could exist so cannot reverse it */
    }
}
