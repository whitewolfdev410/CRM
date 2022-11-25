<?php

namespace App\Modules\Invoice\Services\Builder;

use App\Core\Exceptions\NotImplementedException;
use Illuminate\Container\Container;

class Pdf
{
    /**
     * @var Container
     */
    protected $app;


    /**
     * WorkOrderPdfPrinter constructor.
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Creates PDF file
     *
     * @param $invoiceId
     *
     * @return array
     * @throws NotImplementedException
     */
    public function create($invoiceId)
    {
        $pdf = $this->getPdfClass();
        $pdf->prepare($invoiceId);

        return $pdf->generate();
    }
    
    /**
     * Get PDF class that will be used for Invoice PDF generation
     *
     * @return Pdf
     * @throws NotImplementedException
     */
    protected function getPdfClass()
    {
        $crmUser = config('app.crm_user');
        
        $className = __NAMESPACE__ . '\\' . ucfirst(mb_strtolower($crmUser)) . 'InvoicePdf';

        if (class_exists($className)) {
            return $this->app->make($className);
        } else {
            $exception = $this->app->make(NotImplementedException::class);
            $exception->setData([
                'className' => $className,
                'class' => __CLASS__,
                'line'  => __LINE__,
            ]);
            throw $exception;
        }
    }
}
