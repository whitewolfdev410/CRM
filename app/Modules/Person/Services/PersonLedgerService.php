<?php

namespace App\Modules\Person\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;

/**
 * Class that will retrieve the ledger entries of a Person.
 */
class PersonLedgerService
{
    /**
     * Request object
     *
     * @var Request
     */
    protected $request;

    /**
     * @param Request $request
     */
    public function __construct(
        Request $request
    ) {
        $this->request = $request;
    }

    /**
     * @param int $id
     *
     * @return LengthAwarePaginator
     */
    public function getLedger($id)
    {
        $payments = DB::table('payment')
            ->selectRaw(implode(',', [
                'payment.created_date as `date`',
                'payment.note as `description`',
                "'Payment' as `type`",
                'type.type_value as `method`',
                'payment.payment_id as `entry_id`',
                '-payment.total as `amount`',
            ]))
            ->distinct()
            ->join('type', 'payment.type_id', '=', 'type.type_id')
            ->where('person_id', '=', $id);

        $invoiceEntries = DB::table('invoice_entry')
            ->selectRaw(implode(',', [
                'created_date as `date`',
                'entry_short as `description`',
                "'Invoice entry' as `type`",
                "'' as `method`",
                'invoice_entry_id as `entry_id`',
                'total as `amount`',
            ]))
            ->distinct()
            ->where('person_id', '=', $id);

        /** @var array $entries */
        $entries = $payments
            ->union($invoiceEntries)
            ->orderBy('date')
            ->get();

        $balance = 0;
        $index = 1;
        foreach ($entries as $entry) {
            $balance += $entry->amount;
            $entry->id = $index;
            $entry->balance = $balance;

            $index++;
        }

        $count = count($entries);

        $paginator = new Paginator($entries, $count, $count || 1, 1, [
            'path'  => $this->request->url(),
            'query' => $this->request->query(),
        ]);

        return $paginator;
    }
}
