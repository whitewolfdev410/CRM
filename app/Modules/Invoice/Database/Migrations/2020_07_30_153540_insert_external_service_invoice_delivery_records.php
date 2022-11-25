<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InsertExternalServiceInvoiceDeliveryRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'id';
    
        if (!Schema::hasTable('invoice_delivery')) {
            return;
        }

        DB::insert('
            INSERT INTO invoice_delivery (
                invoice_id,
                method,
                method_detail,
                success,
                status,
                status_timestamp,
                created_at,
                updated_at
            )
            (SELECT
                ej.object_id,
                \'external_service\',
                qj.service,
                ej.success,
                ej.feedback,
                ej.last_completed_at,
                ej.first_completed_at,
                ej.last_completed_at
            FROM external_service_job ej
            JOIN queued_job qj ON qj.id = ej.last_queued_job_id
            WHERE
                qj.table_name = \'invoice\' AND
                ej.type = \'send_invoice\' AND
                NOT EXISTS(SELECT * FROM invoice_delivery WHERE invoice_id = ej.object_id))
        ');
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
