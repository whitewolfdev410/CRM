<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatusToInvoicesBatchesTable extends Migration
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
                if (!in_array('status_type_id', $columns)) {
                    $table->integer('status_type_id')
                        ->unsigned()
                        ->nullable()
                        ->default(0)
                        ->index('status_type_id');
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
