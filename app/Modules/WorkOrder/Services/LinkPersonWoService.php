<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\Trans;
use App\Modules\Activity\Models\Activity;
use App\Modules\Activity\Repositories\ActivityRepository;
use App\Modules\Bill\Models\Bill;
use App\Modules\CalendarEvent\Models\CalendarEvent;
use App\Modules\File\Models\File;
use App\Modules\Kb\Models\ArticleProgress;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\TimeSheet\Repositories\TimeSheetRepository;
use App\Modules\WorkOrder\Exceptions\LpWoAssignedTimeSheetsException;
use App\Modules\WorkOrder\Exceptions\LpWoNotAssignedException;
use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\WorkOrder\Exceptions\LpWoAlreadyConfirmedException;
use App\Modules\WorkOrder\Exceptions\LpWoCurrentlyConfirmedException;
use App\Modules\WorkOrder\Models\DataExchange;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Container\Container;

class LinkPersonWoService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var LinkPersonWoRepository
     */
    protected $linkPersonWoRepository;

    /**
     * Initialize class
     *
     * @param  Container  $app
     * @param  LinkPersonWoRepository  $linkPersonWoRepository
     */
    public function __construct(
        Container $app,
        LinkPersonWoRepository $linkPersonWoRepository
    ) {
        $this->app = $app;
        $this->linkPersonWoRepository = $linkPersonWoRepository;
    }

    /**
     * @param $linkPersonWoId
     *
     * @return bool|null
     * @throws LpWoAssignedTimeSheetsException
     */
    public function remove($linkPersonWoId)
    {
        $linkPersonWo = $this->linkPersonWoRepository->find($linkPersonWoId);

        /** @var TimeSheetRepository $timeSheetRepository */
        $timeSheetRepository = app(TimeSheetRepository::class);
        
        $timeSheets = $timeSheetRepository->getByLinkPersonWoId($linkPersonWoId);
        if ($timeSheets->count()) {
            /** @var LpWoAssignedTimeSheetsException $exp */
            $exp = app(LpWoAssignedTimeSheetsException::class);

            // set exception data to give details
            $exp->setData([
                'link_person_wo_id' => $linkPersonWoId,
                'total_timesheets' => $timeSheets->count()
            ]);

            throw $exp;
        }

        $bills = Bill::where('link_person_wo_id', $linkPersonWoId)
            ->get();
        
        $this->removeRelatedRecords($bills);

        $purchaseOrders = PurchaseOrder::where('link_person_wo_id', $linkPersonWoId)
            ->get();
        
        $this->removeRelatedRecords($purchaseOrders);

        $articleProgress = ArticleProgress::where('link_tablename', 'link_person_wo')
            ->where('link_record_id', $linkPersonWoId)
            ->get();

        $this->removeRelatedRecords($articleProgress);
        
        $activity = Activity::where('table_name', 'link_person_wo')
            ->where('table_id', $linkPersonWoId)
            ->get();

        $this->removeRelatedRecords($activity);
        
        $event = CalendarEvent::where('tablename', 'link_person_wo')
            ->where('record_id', $linkPersonWoId)
            ->get();

        $this->removeRelatedRecords($event);
        
        $dataExchange = DataExchange::where('table_name', 'link_person_wo')
            ->where('record_id', $linkPersonWoId)
            ->get();

        $this->removeRelatedRecords($dataExchange);

        $files = File::where('table_name', 'link_person_wo')
            ->where('table_id', $linkPersonWoId)
            ->get();

        $this->removeRelatedRecords($files);

        return $linkPersonWo->delete();
    }

    /**
     * Remove related records
     * Deleting one by one so that the changes go to the history table
     *
     * @param $items
     */
    private function removeRelatedRecords($items)
    {
        foreach ($items as $item) {
            $item->delete();
        }
    }
}
