<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInfoToInvoiceImportExceptionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'id';
    
        if (!Schema::hasTable('invoice_import_exception')) {
            Schema::create(
                'invoice_import_exception',
                function (Blueprint $table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );
        }

        $columns = Schema::getColumnListing('invoice_import_exception');

        Schema::table(
            'invoice_import_exception',
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array($primaryKeyName, $columns)) {
                    $table->increments($primaryKeyName);
                }
                if (!in_array('amount', $columns)) {
                    $table->decimal('amount', 10)->after('customer')->nullable();
                }
                if (!in_array('invoice_date', $columns)) {
                    $table->date('invoice_date')->after('amount')->nullable();
                }
                if (!in_array('import_data', $columns)) {
                    $table->longText('import_data')->after('invoice_date')->nullable();
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
