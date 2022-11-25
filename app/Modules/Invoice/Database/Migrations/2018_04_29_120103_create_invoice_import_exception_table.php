<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInvoiceImportExceptionTable extends Migration
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
                if (!in_array('invoice_number', $columns)) {
                    $table->string('invoice_number', 30)->index();
                }
                if (!in_array('work_order_number', $columns)) {
                    $table->string('work_order_number')->nullable()->index();
                }
                if (!in_array('customer', $columns)) {
                    $table->string('customer')->nullable();
                }
                if (!in_array('error_message', $columns)) {
                    $table->string('error_message');
                }
                if (!in_array('resolved_at', $columns)) {
                    $table->timestamp('resolved_at')->nullable()->index();
                }
                if (!in_array('created_at', $columns)) {
                    $table->timestamp('created_at')->index();
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
