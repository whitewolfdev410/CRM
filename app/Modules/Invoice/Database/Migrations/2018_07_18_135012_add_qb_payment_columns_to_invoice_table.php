<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class AddQbPaymentColumnsToInvoiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('invoice')) {
            $columns = Schema::getColumnListing('invoice');
            Schema::table(
                'invoice',
                function (Blueprint $table) use ($columns) {
                    if (!in_array('qb_payment_status', $columns)) {
                        $table->string('qb_payment_status', 255)->nullable();
                    }
                    if (!in_array('qb_payment_info', $columns)) {
                        $table->text('qb_payment_info')->nullable();
                    }
                }
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
