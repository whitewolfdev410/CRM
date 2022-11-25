<?php

namespace App\Modules\Invoice\Services\Builder;

use App\Modules\Address\Models\Address;
use App\Modules\Address\Repositories\AddressRepository;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Repositories\InvoiceEntryRepository;
use App\Modules\Invoice\Repositories\InvoiceRepository;
use App\Modules\Payment\Models\PaymentInvoice;
use App\Modules\Payment\Repositories\PaymentInvoiceRepository;
use App\Modules\Person\Models\Person;
use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\System\Repositories\SystemSettingsRepository;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Codedge\Fpdf\Fpdf\Fpdf;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

abstract class InvoicePdf extends Fpdf
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var Person
     */
    protected $billingCompany;

    /**
     * @var Address
     */
    protected $billingCompanyAddress;

    /**
     * @var Invoice
     */
    protected $invoice;

    /**
     * @var Collection
     */
    protected $invoiceEntries;

    /**
     * @var Collection
     */
    protected $payments;
    
    /**
     * @var Person
     */
    protected $person;

    /**
     * @var Person
     */
    protected $projectManager;

    /**
     * @var Address
     */
    protected $serviceAddress;

    /**
     * @var WorkOrder
     */
    protected $workOrder;

    /**
     * @var AddressRepository
     */
    private $addressRepository;

    /**
     * @var array
     */
    private $company;

    /**
     * @var InvoiceRepository
     */
    private $invoiceRepository;

    /**
     * @var InvoiceEntryRepository
     */
    private $invoiceEntryRepository;

    /**
     * @var PaymentInvoiceRepository
     */
    private $paymentInvoiceRepository;
    
    /**
     * @var PersonRepository
     */
    private $personRepository;

    /**
     * @var SystemSettingsRepository
     */
    private $systemSettingsRepository;

    /**
     * @var WorkOrderRepository
     */
    private $workOrderRepository;

    /**
     * InvoicePdf constructor.
     *
     * @param Container                $app
     * @param AddressRepository        $addressRepository
     * @param InvoiceRepository        $invoiceRepository
     * @param InvoiceEntryRepository   $invoiceEntryRepository
     * @param PaymentInvoiceRepository $paymentInvoiceRepository
     * @param PersonRepository         $personRepository
     * @param SystemSettingsRepository $systemSettingsRepository
     * @param WorkOrderRepository      $workOrderRepository
     */
    public function __construct(
        Container $app,
        AddressRepository $addressRepository,
        InvoiceRepository $invoiceRepository,
        InvoiceEntryRepository $invoiceEntryRepository,
        PaymentInvoiceRepository $paymentInvoiceRepository,
        PersonRepository $personRepository,
        SystemSettingsRepository $systemSettingsRepository,
        WorkOrderRepository $workOrderRepository
    ) {
        parent::__construct();

        $this->app = $app;
        $this->addressRepository = $addressRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->invoiceEntryRepository = $invoiceEntryRepository;
        $this->paymentInvoiceRepository = $paymentInvoiceRepository;
        $this->personRepository = $personRepository;
        $this->systemSettingsRepository = $systemSettingsRepository;
        $this->workOrderRepository = $workOrderRepository;
    }

    /**
     * Creates PDF file (without saving it yet)
     */
    abstract public function create();

    /**
     * Register any fonts that will be used when generating PDF
     */
    abstract protected function registerFonts();

    /**
     * Prepare everything that will be needed to generate PDF file
     *
     * @param $invoiceId
     */
    public function prepare($invoiceId)
    {
        $this->loadData($invoiceId);
        $this->create();
    }

    /**
     * Generates pdf
     *
     * @return string
     */
    public function generate()
    {
        return $this->output();
    }

    /**
     * Get fonts directory
     *
     * @return string
     */
    protected function getFontsDirectory()
    {
        return rtrim($this->app->config->get('app.fonts_directory'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Load any necessary data that will be used to generate PDF
     *
     * @param $invoiceId
     */
    protected function loadData($invoiceId)
    {
        $this->company = $this->systemSettingsRepository->getByGroup('company_info', true);
        $this->invoice = $this->invoiceRepository->find($invoiceId);
        $this->invoiceEntries = $this->invoiceEntryRepository->getGroupedEntriesByInvoiceId($invoiceId);
        $this->payments = $this->paymentInvoiceRepository->getByInvoiceId($invoiceId);
        $this->person = $this->personRepository->find(auth()->user()->getPersonId());

        if (!empty($this->invoice->getWorkOrderId())) {
            $this->workOrder = $this->workOrderRepository->find($this->invoice->getWorkOrderId());

            if (!empty($this->workOrder->getProjectManagerPersonId())) {
                $this->projectManager = $this->personRepository->find($this->workOrder->getProjectManagerPersonId());
            }

            $this->getBillingCompanyForWorkOrder();
            $this->getAddressesForWorkOrder();
        } else {
            if (!empty($this->invoice->getPersonId())) {
                $this->getBillingCompanyForInvoice();
                $this->getAddressesForInvoice();
            }
        }
    }

    /**
     * Get company data by key
     *
     * @param      $key
     * @param null $default
     *
     * @return mixed|null
     */
    protected function getCompanyData($key, $default = null)
    {
        if (isset($this->company[$key])) {
            return $this->company[$key];
        }

        return $default;
    }

    protected function fixedNl($string)
    {
        return preg_replace("/\r\n|\\\\r\\\\n/", "\n", $string);
    }

    protected function moneyFormat($number, $prefix = '$')
    {
        return trim($prefix . number_format($number, 2, '.', ','));
    }
    
    /**
     * MultiCell with alignment as in Cell.
     *
     * @param float   $w
     * @param float   $h
     * @param string  $text
     * @param mixed   $border
     * @param int     $ln
     * @param string  $align
     * @param boolean $fill
     */
    protected function MultiAlignCell($w, $h, $text, $border = 0, $ln = 0, $align = 'L', $fill = false)
    {
        // Store reset values for (x,y) positions
        $x = $this->GetX() + $w;
        $y = $this->GetY();

        // Make a call to FPDF's MultiCell
        $this->MultiCell($w, $h, $text, $border, $align, $fill);

        $newY = $this->GetY();
        
        // Reset the line position to the right, like in Cell
        if ($ln == 0) {
            $this->SetXY($x, $y);
        }
        
        return $newY;
    }

    protected function getRealHeight($w, $h, $string, $font = 'Arial', $style = '', $fontSize = 10)
    {
        $fpdf = new Fpdf();
        $fpdf->AddPage();
        $fpdf->SetFont($font, $style, $fontSize);
        $fpdf->setY(0);
        $fpdf->MultiCell($w, $h, $string);

        return $fpdf->getY();
    }
    
    /**
     * Get billing company data for work order
     */
    private function getBillingCompanyForWorkOrder()
    {
        try {
            if (!empty($this->workOrder->getBillingCompanyPersonId())) {
                $this->billingCompany = $this->personRepository->basicFind($this->workOrder->getBillingCompanyPersonId());
            } else {
                if (!empty($this->workOrder->getCompanyPersonId())) {
                    $this->billingCompany = $this->personRepository->basicFind($this->workOrder->getCompanyPersonId());
                }
            }
        } catch (ModelNotFoundException $e) {
        }
    }

    /**
     * Get addresses for work order
     */
    private function getAddressesForWorkOrder()
    {
        if ($this->billingCompany) {
            $this->billingCompanyAddress = $this->addressRepository->getDefaultForPerson($this->billingCompany->getId());
        }

        if (!empty($this->workOrder->getShopAddressId())) {
            $this->serviceAddress = $this->addressRepository->basicFind($this->workOrder->getShopAddressId());
        }
    }

    /**
     * Get billing company data for invoice
     */
    private function getBillingCompanyForInvoice()
    {
        $this->billingCompany = $this->personRepository->basicFind($this->invoice->getPersonId());
    }

    /**
     * Get addresses for invoice
     */
    private function getAddressesForInvoice()
    {
        if ($this->billingCompany) {
            $this->billingCompanyAddress = $this->addressRepository->getDefaultForPerson($this->billingCompany->getId());
            $this->serviceAddress = $this->billingCompanyAddress;
        }
    }
}
