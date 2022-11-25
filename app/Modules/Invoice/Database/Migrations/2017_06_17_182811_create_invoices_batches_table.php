<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInvoicesBatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'invoice_batch_id';

        if (!Schema::hasTable('invoice_batch')) {
            Schema::create(
                'invoice_batch',
                function ($table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );
        }

        $columns = Schema::getColumnListing('invoice_batch');

        Schema::table(
            'invoice_batch',
            function ($table) use ($columns, $primaryKeyName) {
                if (!in_array($primaryKeyName, $columns)) {
                    $table->increments($primaryKeyName);
                }
                if (!in_array('table_name', $columns)) {
                    $table->string('table_name', 24)
                        ->index()
                        ->nullable();
                }
                if (!in_array('table_id', $columns)) {
                    $table->integer('table_id')
                        ->unsigned()
                        ->index()
                        ->nullable();
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
