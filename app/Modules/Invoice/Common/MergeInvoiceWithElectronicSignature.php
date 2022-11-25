<?php

namespace App\Modules\Invoice\Common;

use App\Modules\File\Models\File;
use App\Modules\File\Services\FileService;
use App\Modules\File\Services\SignatureService;
use App\Modules\TimeSheet\Services\TimeSheetService;
use Carbon\Carbon;
use finfo;
use Illuminate\Support\Facades\Log;
use Imagick;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MergeInvoiceWithElectronicSignature
{
    /**
     * @var File
     */
    private $signature;

    /**
     * @var string
     */
    private $invoicePath;

    /**
     * MergeInvoiceWithElectronicSignature constructor.
     *
     * @param  File  $signature
     * @param $invoicePath
     */
    public function __construct(File $signature, $invoicePath)
    {
        $this->signature = $signature;
        $this->invoicePath = $invoicePath;
    }

    public function merge()
    {
        $tmpInvoicePath = '/tmp/invoice_'.uniqid().'.jpg';

        $signatureLink = $this->signature->getLink();

        $signature = $this->getSignature($signatureLink);
        $invoice = $this->getInvoice($this->invoicePath);

        $invoice->compositeImage($signature, Imagick::COMPOSITE_ATOP, 440, 2780);
        $invoice->writeImage($tmpInvoicePath);

        try {
            $this->uploadFile($tmpInvoicePath);
        } catch (\Exception $e) {
            Log::error('Cannot save the generated invoice with a signature ', $e->getTrace());

            throw $e;
        }
    }

    /**
     * @param $signatureLink
     *
     * @return Imagick
     * @throws \ImagickException
     */
    private function getSignature($signatureLink)
    {
        $signature = new SignatureService($signatureLink);
        $signature->convertToPng();
        $signature->changeLineColor();
        $signature->scaleImage();
        
        return $signature->getSignature();
    }

    private function getInvoice($invoicePath)
    {
        $invoice = new Imagick();
        $invoice->setResolution(300, 300);
        $invoice->readImage($invoicePath);
        $invoice->setImageColorspace(255);
        $invoice->setCompressionQuality(95);
        $invoice->setImageFormat('jpg');

        return $invoice;
    }

    /**
     * @param $tmpInvoicePath
     *
     * @return File|null
     * @throws \App\Modules\File\Exceptions\CreateRecordException
     * @throws \App\Modules\File\Exceptions\NoDeviceForVolumeException
     * @throws \App\Modules\File\Exceptions\SaveToStorageException
     */
    private function uploadFile($tmpInvoicePath)
    {
        /** @var FileService $fileService */
        $fileService = app(FileService::class);

        /** @var TimeSheetService $timeSheetService */
        $timeSheetService = app(TimeSheetService::class);

        $fInfo = new finfo(FILEINFO_MIME_TYPE);
        $file = new UploadedFile(
            $tmpInvoicePath,
            basename($tmpInvoicePath),
            $fInfo->file($tmpInvoicePath),
            0,
            true
        );

        $linkPersonWoId = 0;
        if ($this->signature->getTableName() === 'time_sheet') {
            $linkPersonWoId = $timeSheetService->getLinkPersonWoIdByTimeSheetId($this->signature->getTableId());
        }

        return $fileService->upload(
            $file,
            'Combined invoice with an electronic signature',
            'link_person_wo',
            $linkPersonWoId,
            Carbon::now(),
            $this->signature->getPersonId(),
            md5(file_get_contents($tmpInvoicePath)),
            getTypeIdByKey('wo_pictures.invoice'),
            '',
            $linkPersonWoId.'_invoice_signed_'.date('YmdHis').'.jpg',
            0,
            $linkPersonWoId
        );
    }
}
