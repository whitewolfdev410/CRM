<?php

namespace App\Modules\Invoice\Http\Requests;

use App\Http\Requests\Request;
use App\Modules\Invoice\Repositories\InvoiceRepository;
use App\Modules\Quote\Models\Quote;
use App\Modules\Quote\Repositories\QuoteRepository;
use App\Modules\Type\Repositories\TypeRepository;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Support\Facades\Auth;

class InvoiceStoreFromQuoteRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = $this->getRules();

        $data = $this->validationData();
        if (!empty($data['quote_id']) && !$this->canCreateManyInvoices()) {
            $rules['quote_id'][] = 'is_not_invoiced';
            $rules['quote_id'][] = 'has_no_wo_invoice';
        }

        return $rules;
    }

    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function getRules()
    {
        $rules = [
            'quote_id' => [
                'required',
            ],
        ];

        $quoteIdExistsRule = 'exists:quote,quote_id,table_name,work_order';

        if (!$this->canCreateManyInvoices()) {
            $type = $this->getTypeRepository();
            $appr = $type->getIdByKey('quote_status.internal_invoice_approved');
            $quoteIdExistsRule .= ',type_id,' . $appr;
        }
        // @todo verify if in else any type_id is allowed

        $rules['quote_id'][] = $quoteIdExistsRule;

        return $rules;
    }

    /**
     * Verify if user can create many invoices from single quote
     *
     * @return bool
     */
    protected function canCreateManyInvoices()
    {
        return Auth::user()->can('invoice.store-many-from-quote');
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormatterRules()
    {
        return [
            'quote_id' => 'int',
        ];
    }

    /**
     * Register custom validator rules for validator
     *
     * @return \Illuminate\Validation\Validator|mixed
     */
    protected function getValidatorInstance()
    {
        $validator = parent::getValidatorInstance();

        $invRepo = $this->getInvoiceRepository();
        $woRepo = $this->getWorkOrderRepository();
        $quoteRepo = $this->getQuoteRepository();
        $this->isNotInvoiced($validator, $invRepo);
        $this->hasNoWoInvoice($validator, $quoteRepo, $woRepo);

        return $validator;
    }

    /**
     * Verify it there are already any invoice for given quote_id
     *
     * @param $validator
     * @param $invRepo
     */
    public function isNotInvoiced($validator, $invRepo)
    {
        $validator->addImplicitExtension(
            'is_not_invoiced',
            function (
                $attribute,
                $value,
                $parameters,
                $validator
            ) use (
                $invRepo
            ) {
                $nr = $invRepo->getCountForQuote($value);
                if ($nr) {
                    return false;
                }

                return true;
            }
        );
    }

    /**
     * Verify if work order connected to this quote has anything filled as
     * invoice_number. If yes, new invoice cannot be created
     *
     * @param $validator
     * @param $quoteRepo
     * @param $woRepo
     */
    public function hasNoWoInvoice($validator, $quoteRepo, $woRepo)
    {
        $validator->addImplicitExtension(
            'has_no_wo_invoice',
            function (
                $attribute,
                $value,
                $parameters,
                $validator
            ) use (
                $quoteRepo,
                $woRepo
            ) {
                /** @var Quote $quote */
                $quote = $quoteRepo->findSoft($value);
                /* no valid quote - we make this validator pass - required rule
                   will catch it
                */
                if (!$quote) {
                    return true;
                }
                if ($quote && $quote->getTableName() == 'work_order') {
                    /** @var WorkOrder $wo */
                    $wo = $woRepo->findSoft($quote->getTableId());
                    if ($wo && empty($wo->getInvoiceNumber())) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    /**
     * Get Invoice repository
     *
     * @return InvoiceRepository
     */
    protected function getInvoiceRepository()
    {
        return \App::make(InvoiceRepository::class);
    }

    /**
     * Get Work order repository
     *
     * @return WorkOrderRepository
     */
    protected function getWorkOrderRepository()
    {
        return \App::make(WorkOrderRepository::class);
    }

    /**
     * Get Quote repository
     *
     * @return QuoteRepository
     */
    protected function getQuoteRepository()
    {
        return \App::make(QuoteRepository::class);
    }

    /**
     * Get Type repository
     *
     * @return TypeRepository
     */
    protected function getTypeRepository()
    {
        return \App::make(TypeRepository::class);
    }
}
