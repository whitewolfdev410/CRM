<?php

namespace App\Modules\Invoice\Jobs;

use App\Jobs\Job;
use App\Modules\File\Models\File;
use App\Modules\Invoice\Common\MergeInvoiceWithElectronicSignature;
use App\Modules\Invoice\Services\InvoiceService;
use App\Modules\WorkOrder\Models\WorkOrder;
use Illuminate\Contracts\Queue\ShouldQueue;

class MergeInvoiceWithElectronicSignatureJob extends Job implements ShouldQueue
{
    /**
     * @var WorkOrder
     */
    private $workOrder;

    /**
     * @var File
     */
    private $file;

    public function __construct($workOrder, $file)
    {
        $this->workOrder = $workOrder;
        $this->file = $file;
    }

    public function handle()
    {
        $workOrderNumber = $this->workOrder->getWorkOrderNumber();
        
        $invoicePath = InvoiceService::getPdfInvoicePath($workOrderNumber);
        if (!$invoicePath) {
            throw new \Exception("File with pdf invoice for Work Order $workOrderNumber does not exist");
        }
        
        $invoice = new MergeInvoiceWithElectronicSignature($this->file, $invoicePath);
        $invoice->merge();
    }
}
