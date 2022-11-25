<?php

namespace App\Modules\History\Services;

use App\Modules\History\Models\HistoryInsertStatus;
use App\Modules\History\Models\HistoryWoStatus;
use App\Modules\History\Models\HistoryWoPartsStatus;
use App\Modules\History\Models\HistoryWoInvoiceStatus;
use App\Modules\History\Models\HistoryWoBillStatus;
use App\Modules\History\Models\HistoryLpwoStatus;
use App\Modules\History\Models\HistoryLpwoTechStatus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Contracts\Container\Container;

class HistoryInsertService
{

    /**
     * @var Container
     */
    protected $app;

    /**
     * HistoryInsertService constructor.
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Function will run syncing
     * @param int $limit
     */
    public function run($limit = 100)
    {
        // get all entries from history_insert_status table
        $sql = "SELECT * FROM history_insert_status ORDER BY id ASC";
        $list = DB::select(DB::raw($sql));
        // for each entry start searching statuses for import
        foreach ($list as $k => $l) {
            $this->searchAndAdd($l, $limit);
        }
    }

    /**
     * Function will insert all history rows (by limit) and will update history_insert_status table with new last_history_id
     * @param $historyInsertStatus
     * @param $limit
     */
    public function searchAndAdd($historyInsertStatus, $limit)
    {
        $sql = "SELECT  history.history_id, history.`record_id`, history.`value_to` AS type_id, (SELECT type.type_value from type where type.type_id = history.value_to) as type_value, history.date_created FROM history WHERE ";
        $sql .= " history.`tablename` = '{$historyInsertStatus->table_name}' AND history.`columnname` = '{$historyInsertStatus->insert_type}' AND history.`value_to` > 0 AND history.`history_id` > {$historyInsertStatus->last_history_id} ";
        $sql .= " ORDER BY history_id ASC LIMIT ".$limit;

        $list = DB::select(DB::raw($sql));
        $historyInsertStatusObj = $this->app[HistoryInsertStatus::class];
        $historyInsertStatusObj = $historyInsertStatusObj->where('id', $historyInsertStatus->id)->first();

        $currentObject = $this->getCurrentObjectByName($historyInsertStatus->insert_table);
        $columnName = $historyInsertStatus->insert_column;
        foreach ($list as $k => $l) {
            $tmp = $currentObject->newInstance();
            $tmp->$columnName = $l->record_id;
            $tmp->type_id = $l->type_id;
            $tmp->type_value = $l->type_value;
            $tmp->date_created = $l->date_created;
            $tmp->save();

            $historyInsertStatusObj->last_history_id = $l->history_id;
            $historyInsertStatusObj->save();
        }
    }

    /**
     * Function will get proper class container for table_name
     * @param $name
     * @return mixed
     * @throws Exception
     */
    public function getCurrentObjectByName($name)
    {
        switch ($name) {
            case 'history_wo_status':
                return $this->app[HistoryWoStatus::class];
                break;
            case 'history_wo_parts_status':
                return $this->app[HistoryWoPartsStatus::class];
                break;
            case 'history_wo_bill_status':
                return $this->app[HistoryWoBillStatus::class];
                break;
            case 'history_wo_invoice_status':
                return $this->app[HistoryWoInvoiceStatus::class];
                break;
            case 'history_lpwo_status':
                return $this->app[HistoryLpwoStatus::class];
                break;
            case 'history_lpwo_tech_status':
                return $this->app[HistoryLpwoTechStatus::class];
                break;
            default:
                throw new Exception("History Insert: Cannot get object for table_name: ".$name);
                break;
        }
    }
}
