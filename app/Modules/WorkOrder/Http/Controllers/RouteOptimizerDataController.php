<?php

namespace App\Modules\WorkOrder\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class RouteOptimizerDataController
 *
 * @package App\Modules\WorkOrder\Http\Controllers
 */
class RouteOptimizerDataController extends Controller
{
    public function getAssignedForTomorrow(Request $request)
    {
        $date = $request->input('date', '');
        $dateRegex = "/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/";

        if (!$date || !preg_match($dateRegex, $date)) {
            $datetime = new DateTime('tomorrow');
            $date = $datetime->format('Y-m-d');
        }

        $columns = "lpwo.link_person_wo_id,
    DATE(lpwo.scheduled_date) AS scheduled_date,
    lpwo.person_id,
    t1.type_key AS tech_status,
    p.custom_1 AS first_name,
    p.custom_3 AS last_name,
    wo.work_order_id,
    wo.work_order_number,
    sr.sl_record_id AS customer_id,
    a.address_name,
    a.address_1,
    a.address_2,
    a.city,
    a.state,
    a.zip_code,
    a.country,
    a.latitude,
    a.longitude
";

        $conditions = "DATE(lpwo.scheduled_date) = '{$date}'
        AND lpwo.is_disabled = 0
        AND t1.type_key IN ('tech_status.assigned') ";

        $data = DB::table('link_person_wo AS lpwo')
        ->select(DB::raw($columns))
        ->leftJoin('work_order AS wo', 'wo.work_order_id', '=', 'lpwo.work_order_id')
        ->leftJoin('address AS a', 'a.address_id', '=', 'wo.shop_address_id')
        ->leftJoin('person AS p', 'p.person_id', '=', 'lpwo.person_id')
        ->leftJoin('type AS t1', 't1.type_id', '=', 'lpwo.tech_status_type_id')
        ->leftJoin('sl_records AS sr', function ($join) {
            $join->on('sr.record_id', '=', 'a.person_id')
                ->where('sr.table_name', '=', 'person');
        })
        ->whereRaw(DB::raw($conditions))
        ->get();

        return response()->json($data);
    }
}
