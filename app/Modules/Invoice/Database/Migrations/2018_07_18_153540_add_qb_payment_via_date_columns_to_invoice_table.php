<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class AddQbPaymentViaDateColumnsToInvoiceTable extends Migration
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
                    if (!in_array('qb_payment_via', $columns)) {
                        $table->string('qb_payment_via', 24)->nullable();
                    }
                    if (!in_array('qb_payment_date', $columns)) {
                        $table->dateTime('qb_payment_date')->nullable();
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
