<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\PushNotification\Services\PushNotificationFcmService;
use App\Modules\WorkOrder\Exceptions\WoInvoicedCannotAddVendorsException;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WorkOrderAddVendorsService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var WorkOrderRepository
     */
    protected $woRepo;

    /**
     * @var PersonRepository
     */
    protected $personRepo;

    /**
     * @var LinkPersonWoRepository
     */
    protected $lpWoRepo;

    /**
     * Initialize class
     *
     * @param Container $app
     * @param WorkOrderRepository $woRepo
     * @param PersonRepository $personRepo
     * @param LinkPersonWoRepository $lpWoRepo
     */
    public function __construct(
        Container $app,
        WorkOrderRepository $woRepo,
        PersonRepository $personRepo,
        LinkPersonWoRepository $lpWoRepo
    ) {
        $this->app = $app;
        $this->woRepo = $woRepo;
        $this->personRepo = $personRepo;
        $this->lpWoRepo = $lpWoRepo;
    }

    /**
     * Runs procedure of assigning multiple vendors to work order
     *
     * @param int $workOrderId
     * @param string $jobType
     * @param array $vendors
     * @param int|null $recallLinkPersonWoId
     *
     * @return array
     * @throws WoInvoicedCannotAddVendorsException
     */
    public function run(
        $workOrderId,
        $jobType,
        array $vendors,
        $recallLinkPersonWoId
    ) {
        // we get work order just to make sure we assign vendors to work order
        // that exists and to compare whether work order has been changed
        /** @var WorkOrder $wo */
        $wo = $this->woRepo->find($workOrderId);

        // work order already invoiced - throw exception
        if ($wo->getInvoiceStatusTypeId() == getTypeIdByKey('wo_billing_status.invoiced')) {
            /** @var WoInvoicedCannotAddVendorsException $exp */
            $exp = $this->app->make(WoInvoicedCannotAddVendorsException::class);
            throw $exp;
        }

        if (!$recallLinkPersonWoId) {
            $recallLinkPersonWoId = 0;
        }

        // if there are any vendors, for each vendor we need its kind - we grab
        // them in one query for all vendors and need to assign kind for each
        // vendor before assigning vendors to work order
        if ($vendors) {
            $vendorArray = [];

            // get vendors ids
            foreach ($vendors as $vendor) {
                $vendorArray[] = $vendor['person_id'];
            }

            // get person_id and kind for all vendors
            $persons = $this->personRepo->find(
                $vendorArray,
                ['person_id', 'kind'],
                false
            );

            // group data by person_id
            if ($persons) {
                $persons = $persons->groupBy('person_id');
            }

            // for each vendor assign kind to record so we could assign this
            // vendor for work order
            foreach ($vendors as $key => $vendor) {
                $kind = $persons->get($vendor['person_id']);
                if ($kind !== null) {
                    $kind = $kind[0]->kind;
                }

                $vendors[$key]['kind'] = $kind;
            }
        }

        // now we assign vendors in loop for work order
        $items = [];
        $statusTypeId = null;

        DB::transaction(function () use (
            $vendors,
            $workOrderId,
            $jobType,
            $recallLinkPersonWoId,
            &$items,
            &$statusTypeId
        ) {
            foreach ($vendors as $vendor) {
                list($record, $newStatusTypeId) =
                    $this->lpWoRepo->addSingleVendorToWorkOrder(
                        (int)$workOrderId,
                        $vendor['person_id'],
                        $vendor['kind'],
                        $jobType,
                        $recallLinkPersonWoId
                    );
                
                if ($record) {
                    $items[] = $record;
                }
                
                if ($newStatusTypeId !== null) {
                    $statusTypeId = $newStatusTypeId;
                }
            }
        });

        // if there is $statusTypeId set, we compare it with initial work order
        // status anmd if it's different we want to show that work order status
        // has been changed and show changed status value
        if ($statusTypeId !== null &&
            $statusTypeId != $wo->getWoStatusTypeId()
        ) {
            $changed['work_order'] = [
                'wo_status_type_id' => $statusTypeId,
                'wo_status_type_id_value' => getTypeValueById($statusTypeId),
            ];
        } else {
            $changed = null;
        }

        return [$items, $changed];
    }
}
