<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddBatchInvoicesStatuses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('type', function ($table) {
            $statusType = 'invoices_batches';
            $statuses = [
                [
                    'type_key' => 'invoices_batches.sent',
                    'type_value' => 'Sent'
                ],
                [
                    'type_key' => 'invoices_batches.processed',
                    'type_value' => 'Processed'
                ],
                [
                    'type_key' => 'invoices_batches.failed',
                    'type_value' => 'Failed'
                ],
                [
                    'type_key' => 'invoices_batches.new',
                    'type_value' => 'New'
                ]
            ];

            foreach ($statuses as $status) {
                DB::table('type')->insert(
                    $status + [
                        'type' => $statusType
                    ]
                );
            }
        });
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
