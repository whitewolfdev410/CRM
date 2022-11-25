<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddReportedAtToInvoiceImportExceptionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('invoice_import_exception')) {
            $columns = Schema::getColumnListing('invoice_import_exception');

            if (!in_array('reported_at', $columns)) {
                Schema::table('invoice_import_exception', function (Blueprint $table) use ($columns) {
                    $table->timestamp('reported_at')->index()->after('error_message');
                });

                DB::update('UPDATE invoice_import_exception SET reported_at = created_at');
            }
        }
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
