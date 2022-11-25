<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\WorkOrder\Exceptions\TooManyVendorsItemsException;
use App\Modules\WorkOrder\Exceptions\VendorsAssignBulkException;
use App\Services\BulkValidatorService;
use Illuminate\Support\Facades\App;

class WorkOrderVendorsAssignValidatorService extends BulkValidatorService
{
    /**
     * Throws GpsLocationBulkException in case data is invalid
     *
     * @throws VendorsAssignBulkException
     */
    protected function invalidDataAction()
    {
        $validationException = App::make(VendorsAssignBulkException::class);
        $validationException->setFields($this->errors());

        throw $validationException;
    }

    /**
     * Validates all data
     *
     * @throws TooManyVendorsItemsException
     */
    public function validate()
    {
        // first we will check number of items
        $count = count($this->request->input($this->dataKey));

        if ($count > $this->getMaxItems()) {
            $this->tooManyItemsAction($count);
        }

        parent::validate();
    }

    /**
     * Get maximum allowed number of items
     *
     * @return int
     */
    protected function getMaxItems()
    {
        return config('modconfig.work_order.assign_vendors_bulk_max_items', 20);
    }

    /**
     * Throw exception that too many items has been sent
     *
     * @param int $count
     * @throws TooManyVendorsItemsException
     */
    protected function tooManyItemsAction($count)
    {
        /** @var TooManyVendorsItemsException $exp */
        $exp = App::make(TooManyVendorsItemsException::class);
        $exp->setData(['max_items' => $this->getMaxItems(), 'items' => $count]);

        throw $exp;
    }
}
