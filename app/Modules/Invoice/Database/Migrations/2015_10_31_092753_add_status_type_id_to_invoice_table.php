<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class AddStatusTypeIdToInvoiceTable extends Migration
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
