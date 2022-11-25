<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddQuoteStepNameToInvoiceEntryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'invoice_entry_id';
    
        if (!Schema::hasTable('invoice_entry')) {
            Schema::create(
                'invoice_entry',
                function (Blueprint $table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );
        }

        $columns = Schema::getColumnListing('invoice_entry');

        Schema::table(
            'invoice_entry',
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array($primaryKeyName, $columns)) {
                    $table->increments($primaryKeyName);
                }
                if (!in_array('step_name', $columns)) {
                    $table->string('step_name', 128)->nullable();
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
