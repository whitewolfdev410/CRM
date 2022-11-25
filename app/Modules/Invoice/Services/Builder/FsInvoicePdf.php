<?php
/**
 * Created by PhpStorm.
 * User: Kamil Ciszewski
 * Date: 25.10.2019
 * Time: 11:27
 */

namespace App\Modules\Invoice\Services\Builder;

use App\Modules\Invoice\Models\InvoiceEntry;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;

class FsInvoicePdf extends InvoicePdf
{
    private $defaultFontSize = 9.5;
    private $defaultHeight = 5;
    private $maxWidth = 185;
    private $tmpY = 0;

    /**
     * @var array
     */
    private $labTechDevices = [];

    /**
     * {@inheritdoc}
     */
    public function create()
    {
        $this->SetTitle('Invoice - ' . $this->invoice->getId() . ' - ' . date('m/d/Y H:i:s'));
        $this->SetAuthor('Friendly Solutions Corp.');
        $this->SetCreator('CRM created by Friendly Solutions Corp - www.friendly-solutions.com');

        $this->SetMargins(15, 60, 15);
        $this->SetAutoPageBreak(true, 30);
        $this->AliasNbPages();
        
        $this->registerFonts();
        $this->AddPage();

        $this->addCompanyData();
        $this->addInvoiceNumberAndDate();
        $this->addBillTo();
        $this->addServiceAddress();
        $this->addRequestDescription();
        $this->addLabTechDevices();
        $this->addTechnicians();
        $this->addJobDescription();
        $this->addEntries();
        $this->addPay();
        $this->addPayments();
    }

    protected function loadData($invoiceId)
    {
        parent::loadData($invoiceId);
        
        $this->getlabTechDevices();
    }

    protected function registerFonts()
    {
        // set fonts directory
        if (!defined('_SYSTEM_TTFONTS')) {
            define('_SYSTEM_TTFONTS', $this->getFontsDirectory());
        }
    }

    public function Header()
    {
        $logo = $this->getCompanyData('logo1');

        $this->setY(3);
        $this->Image($logo, $this->GetX(), $this->GetY(), 45);

        $this->setY(13);
        $this->SetFont('Arial', 'B', 10);
        $this->setX(180);
        $this->Cell(20, 5, 'INVOICE', 0, 0, 'R');

        $this->setY(30);
        $this->tmpY = 30;
    }

    public function Footer()
    {
        $this->SetY(-20);
        $this->SetFont('Arial', '', $this->defaultFontSize);
        $this->Cell($this->maxWidth, $this->defaultHeight, 'THANK YOU FOR YOUR BUSINESS', 0, 0, 'C');

        $this->SetY(-13);
        $this->Cell($this->maxWidth + 5, 5, "Page " . $this->PageNo() . "/{nb}", 0, 1, 'R');
    }

    public function generate()
    {
        $fileName = 'invoice_' . $this->invoice->getId() . '.pdf';
        
        return $this->output('', $fileName);
    }

    private function addCompanyData()
    {
        $phone = $this->getCompanyData('tel');
        $fax = $this->getCompanyData('fax');

        $this->setY(25);
        $this->SetFont('Arial', '', $this->defaultFontSize);

        $this->Cell(40, $this->defaultHeight, $this->getCompanyData('full_name'));
        $this->Ln();
        $this->Cell(40, $this->defaultHeight, $this->getCompanyData('address1'));

        if ($this->getCompanyData('address2')) {
            $this->Ln();
            $this->Cell(40, $this->defaultHeight, $this->getCompanyData('address2'));
        }

        $city = $this->getCompanyData('city') . ', '
            . $this->getCompanyData('state') . ' '
            . $this->getCompanyData('zip');

        $this->Ln();
        $this->Cell(40, $this->defaultHeight, $city);
        $this->Ln(8);

        if ($phone) {
            $this->SetFont('Arial', 'B', $this->defaultFontSize);
            $this->Cell(12, $this->defaultHeight, 'Phone:');
            $this->SetFont('Arial', '', $this->defaultFontSize);
            $this->Cell(28, $this->defaultHeight, $phone);
            $this->Ln();
        }

        if ($fax) {
            $this->SetFont('Arial', 'B', $this->defaultFontSize);
            $this->Cell(8, $this->defaultHeight, 'Fax:');
            $this->SetFont('Arial', '', $this->defaultFontSize);
            $this->Cell(32, $this->defaultHeight, $fax);
        }
    }

    private function addInvoiceNumberAndDate()
    {
        $this->setY(20);
        $this->setX(120);

        $this->SetFont('Arial', 'B', $this->defaultFontSize);
        $this->Cell(30, $this->defaultHeight, 'Invoice #:', null, null, 'R');
        $this->SetFont('Arial', '', $this->defaultFontSize);
        $this->Cell(50, $this->defaultHeight, $this->invoice->getId(), null, null, 'R');
        $this->Ln();

        $this->setX(120);
        $this->SetFont('Arial', 'B', $this->defaultFontSize);
        $this->Cell(30, $this->defaultHeight, 'Invoice date:', null, null, 'R');
        $this->SetFont('Arial', '', $this->defaultFontSize);
        $this->Cell(50, $this->defaultHeight, $this->invoice->getDateInvoice(), null, null, 'R');

        if ($this->workOrder && !empty($this->workOrder->getWorkOrderNumber())) {
            $this->Ln();
            $this->setX(120);
            $this->SetFont('Arial', 'B', $this->defaultFontSize);
            $this->Cell(30, $this->defaultHeight, 'Work Order #:', null, null, 'R');
            $this->SetFont('Arial', '', $this->defaultFontSize);
            $this->Cell(50, $this->defaultHeight, $this->workOrder->getWorkOrderNumber(), null, null, 'R');
        }

        if (!empty($this->projectManager)) {
            $projectManager = $this->projectManager->getCustom1() . ' ' . $this->projectManager->getCustom3();

            $this->Ln();
            $this->setX(120);
            $this->SetFont('Arial', 'B', $this->defaultFontSize);
            $this->Cell(30, $this->defaultHeight, 'Project Manager:', null, null, 'R');
            $this->SetFont('Arial', '', $this->defaultFontSize);
            $this->Cell(50, $this->defaultHeight, $projectManager, null, null, 'R');
        }

        if ($this->workOrder) {
            if ($this->workOrder->getNotToExceed() > 0) {
                $this->Ln();
                $this->setX(120);
                $this->SetFont('Arial', 'B', $this->defaultFontSize);
                $this->Cell(30, $this->defaultHeight, 'NTE Amount:', null, null, 'R');
                $this->SetFont('Arial', '', $this->defaultFontSize);
                $this->Cell(50, $this->defaultHeight, $this->workOrder->getNotToExceed(), null, null, 'R'); //toMoney
            }

            if (!empty($this->workOrder->getRequestedBy())) {
                $this->Ln();
                $this->setX(120);
                $this->SetFont('Arial', 'B', $this->defaultFontSize);
                $this->Cell(30, $this->defaultHeight, 'Work Request By:', null, null, 'R');
                $this->SetFont('Arial', '', $this->defaultFontSize);
                $this->Cell(50, $this->defaultHeight, $this->workOrder->getRequestedBy(), null, null, 'R');
            }

            if (!empty($this->workOrder->getCreatedAt())) {
                $this->Ln();
                $this->setX(120);
                $this->SetFont('Arial', 'B', $this->defaultFontSize);
                $this->Cell(30, $this->defaultHeight, 'WO Created Date:', null, null, 'R');
                $this->SetFont('Arial', '', $this->defaultFontSize);
                $this->Cell(50, $this->defaultHeight, $this->workOrder->getCreatedAt(), null, null, 'R');
            }

            if (!empty($this->workOrder->getActualCompletionDate())) {
                $this->Ln();
                $this->setX(120);
                $this->SetFont('Arial', 'B', $this->defaultFontSize);
                $this->Cell(30, $this->defaultHeight, 'WO Completion Date:', null, null, 'R');
                $this->SetFont('Arial', '', $this->defaultFontSize);
                $this->Cell(50, $this->defaultHeight, $this->workOrder->getActualCompletionDate(), null, null, 'R');
            }
        }
    }

    private function addBillTo()
    {
        $this->setX($this->lMargin);
        $this->setY(60);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(30, $this->defaultHeight, 'Bill To:');
        $this->SetFont('Arial', '', $this->defaultFontSize);

        $this->Ln();
        $this->Cell(50, $this->defaultHeight, $this->billingCompany->getCustom1());

        $this->Ln();
        $this->Cell(50, $this->defaultHeight, $this->billingCompanyAddress->getAddress1());

        $city = $this->billingCompanyAddress->getCity() . ', '
            . $this->billingCompanyAddress->getState() . ' '
            . $this->billingCompanyAddress->getZipCode();

        $this->Ln();
        $this->Cell(50, $this->defaultHeight, $city);
    }

    private function addServiceAddress()
    {
        $this->setY(60);
        $this->setX(100);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(30, $this->defaultHeight, 'Service Address:');
        $this->SetFont('Arial', '', $this->defaultFontSize);

        $this->Ln();
        $this->setX(100);
        $this->Cell(50, $this->defaultHeight, $this->serviceAddress->getAddressName());

        $this->Ln();
        $this->setX(100);
        $this->Cell(50, $this->defaultHeight, $this->serviceAddress->getAddress1());

        $city = $this->serviceAddress->getCity() . ', '
            . $this->serviceAddress->getState() . ' '
            . $this->serviceAddress->getZipCode();

        $this->Ln();
        $this->setX(100);
        $this->Cell(50, $this->defaultHeight, $city);
        $this->Ln();
    }

    private function addRequestDescription()
    {
        if ($this->invoice->getCustomerRequestDescription()) {
            $this->Ln(5);
            $this->setX($this->lMargin);

            $this->SetFont('Arial', 'B', 10);
            $this->Cell($this->maxWidth, $this->defaultHeight, 'Customer Request:', 'B');
            $this->SetFont('Arial', '', $this->defaultFontSize);

            $this->Ln(5);
            $this->MultiCell($this->maxWidth, $this->defaultHeight, $this->fixedNl(
                $this->invoice->getCustomerRequestDescription()
            ));
        }
    }

    private function addLabTechDevices()
    {
        if (!empty($this->labTechDevices)) {
            $this->Ln(5);
            $this->setX($this->lMargin);

            $this->SetFont('Arial', 'B', 10);
            $this->Cell($this->maxWidth, $this->defaultHeight, 'Devices worked on:', 'B');
            $this->SetFont('Arial', '', $this->defaultFontSize);

            $technicians = array_map(function ($item) {
                return $item->Name;
            }, $this->labTechDevices);

            $this->Ln(5);
            $this->MultiCell($this->maxWidth, $this->defaultHeight, implode(' ', $technicians));
        }
    }

    private function addTechnicians()
    {
        if ($this->workOrder) {
            $linkPersons = $this->workOrder->linkedPersons()->with('person')->get();

            if (count($linkPersons)) {
                $this->Ln(5);
                $this->setX($this->lMargin);

                $this->SetFont('Arial', 'B', 10);
                $this->Cell($this->maxWidth, $this->defaultHeight, 'Technicians who worked on this:', 'B');
                $this->SetFont('Arial', '', $this->defaultFontSize);

                $technicians = $linkPersons->map(function ($item) {
                    return '[' . $item->person->getName() . ']';
                })->toArray();

                $this->Ln(5);
                $this->MultiCell($this->maxWidth, $this->defaultHeight, implode(' ', $technicians));
            }
        }
    }

    private function addJobDescription()
    {
        $jobDescription = trim($this->invoice->getJobDescription());

        if (!empty($jobDescription)) {
            $this->Ln(5);
            $this->setX($this->lMargin);

            $this->SetFont('Arial', 'B', 10);
            $this->Cell($this->maxWidth, $this->defaultHeight, 'Job Description:', 'B');
            $this->SetFont('Arial', '', $this->defaultFontSize);

            $this->Ln(5);
            $this->MultiCell($this->maxWidth, $this->defaultHeight, $this->fixedNl($jobDescription));
        }
    }

    private function addEntries()
    {
        $this->Ln(5);
        $this->SetFillColor(230, 230, 230);
        $this->Cell($this->maxWidth * 0.68, $this->defaultHeight, 'Description', 'TL', 0, 'L', true);
        $this->Cell($this->maxWidth * 0.08, $this->defaultHeight, 'Qty', 'T', 0, 'C', true);
        $this->Cell($this->maxWidth * 0.12, $this->defaultHeight, 'Price', 'T', 0, 'C', true);
        $this->Cell($this->maxWidth * 0.12, $this->defaultHeight, 'Total', 'TR', 0, 'C', true);

        foreach ($this->invoiceEntries as $group) {
            if (count($group['entries'])) {
                $this->Ln();
                $this->Cell($this->maxWidth, $this->defaultHeight, $group['name'], 1, 0, 'L', true);
                $this->Ln();

                $this->tmpY = $this->GetY();

                /** @var InvoiceEntry $entry */
                foreach ($group['entries'] as $entry) {
                    $height = $this->getRealHeight($this->maxWidth * 0.68, $this->defaultHeight, $entry->getEntryLong());

                    //if the item does not fit on the page we have to move it to the next one
                    if ($this->getY() + $height > 265) {
                        $this->AddPage();
                    } else {
                        $this->setY($this->tmpY);
                    }

                    $y2 = $this->MultiAlignCell(
                        $this->maxWidth * 0.68,
                        $this->defaultHeight,
                        $entry->getEntryLong(),
                        1
                    );

                    $h = $y2 - $this->tmpY;

                    $total = ($entry->getTaxAmount() > 0 ? 'T ' : '') . $this->moneyFormat($entry->getTotal());

                    $this->Cell($this->maxWidth * 0.08, $h, $this->moneyFormat($entry->getQty(), ''), 1, 0, 'R');
                    $this->Cell($this->maxWidth * 0.12, $h, $this->moneyFormat($entry->getPrice()), 1, 0, 'R');
                    $this->Cell($this->maxWidth * 0.12, $h, $total, 1, 0, 'R');

                    $this->tmpY = $y2;
                }

                $this->Ln();
                $this->SetFont('Arial', 'B', $this->defaultFontSize);
                $this->Cell($this->maxWidth * 0.88, $this->defaultHeight, 'Total ' . $group['name'] . ':', 0, 0, 'R');
                $this->SetFont('Arial', '', $this->defaultFontSize);
                $this->Cell(
                    $this->maxWidth * 0.12,
                    $this->defaultHeight,
                    $this->moneyFormat($group['total']),
                    1,
                    0,
                    'R'
                );
            }
        }

        $this->Ln();
        $this->SetFont('Arial', 'B', $this->defaultFontSize);
        $this->Cell($this->maxWidth * 0.88, $this->defaultHeight, 'Total Tax:', 0, 0, 'R');
        $this->Cell(
            $this->maxWidth * 0.12,
            $this->defaultHeight,
            $this->moneyFormat($this->invoice->getTotalTax()),
            1,
            0,
            'R'
        );

        $this->Ln();
        $this->SetFont('Arial', 'B', $this->defaultFontSize);
        $this->Cell($this->maxWidth * 0.88, $this->defaultHeight, 'Total Invoice:', 0, 0, 'R');
        $this->Cell(
            $this->maxWidth * 0.12,
            $this->defaultHeight,
            $this->moneyFormat($this->invoice->getTotalWithTax()),
            1,
            0,
            'R'
        );
        $this->Ln();
    }

    private function addPay()
    {
        if (!$this->invoice->getPaid()) {
            $link = 'https://crm.friendlysol.com/webaccess/invoice_payment?from_invoice=1';

            $this->ln(10);
            $this->SetFillColor(255, 255, 0);
            $this->SetFont('Arial', '', $this->defaultFontSize + 3);
            $this->Cell(
                $this->maxWidth,
                $this->defaultHeight,
                'Click Here To Pay This Invoice via our Secure Customer Portal',
                0,
                0,
                'C',
                true,
                $link
            );
            $this->ln();
        }
    }

    private function addPayments()
    {
        if ($this->payments->count()) {
            $this->Ln(5);
            $this->SetFillColor(146, 228, 146);
            $this->SetFont('Arial', 'B', $this->defaultFontSize);
            $this->Cell(55, $this->defaultHeight, 'Payments applied to this invoice:', 0, 0, 'L', true);

            $this->Ln(10);
            $this->SetFillColor(230, 230, 230);
            $this->SetFont('Arial', '', $this->defaultFontSize);
            $this->Cell($this->maxWidth * 0.2, $this->defaultHeight, 'Date', 1, 0, 'C', true);
            $this->Cell($this->maxWidth * 0.2, $this->defaultHeight, 'Paid with', 1, 0, 'C', true);
            $this->Cell($this->maxWidth * 0.2, $this->defaultHeight, 'Reference #', 1, 0, 'C', true);
            $this->Cell($this->maxWidth * 0.2, $this->defaultHeight, 'Amount Received', 1, 0, 'C', true);
            $this->Cell($this->maxWidth * 0.2, $this->defaultHeight, 'Applied Amount', 1, 0, 'C', true);

            $totalPaymentSize = 0;
            foreach ($this->payments as $payment) {
                $note = strlen($payment->note) > 10 ? substr($payment->note, 0, 10) . '...' : $payment->note;
                $totalPaymentSize += $payment->paymentsize;

                $this->ln();
                $this->Cell($this->maxWidth * 0.2, $this->defaultHeight, $payment->payment_date, 1);
                $this->Cell($this->maxWidth * 0.2, $this->defaultHeight, $payment->type_value, 1);
                $this->Cell($this->maxWidth * 0.2, $this->defaultHeight, $note, 1);
                $this->Cell(
                    $this->maxWidth * 0.2,
                    $this->defaultHeight,
                    $this->moneyFormat($payment->total),
                    1,
                    0,
                    'R'
                );
                $this->Cell(
                    $this->maxWidth * 0.2,
                    $this->defaultHeight,
                    $this->moneyFormat($payment->paymentsize),
                    1,
                    0,
                    'R'
                );
            }

            $this->ln();
            $this->SetFillColor(146, 228, 146);
            $this->Cell($this->maxWidth * 0.8, $this->defaultHeight, 'Total paid:', 1, 0, 'R', true);
            $this->Cell(
                $this->maxWidth * 0.2,
                $this->defaultHeight,
                $this->moneyFormat($totalPaymentSize),
                1,
                0,
                'R',
                true
            );
        }
    }

    private function getlabTechDevices()
    {
        if (!empty($this->workOrder)) {
            $keys = DB::table('link_labtech_wo')
                ->where('work_order_id', $this->workOrder->getId())
                ->pluck('labtech_table_name', 'labtech_record_id')
                ->all();

            if ($keys) {
                try {
                    $labtech = app(DatabaseManager::class)->connection('labtech');

                    foreach ($keys as $id => $table) {
                        $column = null;

                        switch ($table) {
                            case "computers":
                                $column = 'ComputerID';
                                break;
                            case "networkdevices":
                                $column = 'DeviceID';
                                break;
                        }

                        if ($column) {
                            $this->labTechDevices[] = $labtech->table($table)
                                ->where($column, $id)
                                ->first();
                        }
                    }
                } catch (\Exception $e) {
                }
            }
        }
    }
}
