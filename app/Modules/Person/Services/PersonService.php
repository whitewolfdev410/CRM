<?php

namespace App\Modules\Person\Services;

use App\Helpers\ExcelExport;
use App\Helpers\Exports\ExportExcelSource;
use App\Modules\Person\Models\Person;
use App\Modules\Person\Repositories\LinkPersonCompanyRepository;
use App\Modules\Person\Repositories\PersonRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;

/**
 * Class that will retrieve the ledger entries of a Person.
 */
class PersonService
{
    /**
     * Request object
     *
     * @var Request
     */
    protected $request;
    
    /**
     * @var PersonRepository
     */
    private $personRepository;

    /**
     * @param Request $request
     */
    public function __construct(PersonRepository $personRepository)
    {
        $this->personRepository = $personRepository;
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

    /**
     * @param $personId
     *
     * @return array|null
     */
    public function getPersonData($personId)
    {
        /** @var Person $person */
        $person = Person::find($personId);
        if ($person) {
            /** @var LinkPersonCompanyRepository $linkPersonCompanyRepository */
            $linkPersonCompanyRepository = app(LinkPersonCompanyRepository::class);
            
            /** @var Person $company */
            $company = $linkPersonCompanyRepository->getCompany($personId);
            $companyName = $company ? $company->getName() : null;
            
            return [
                'person_id' => $personId,
                'person_name' => $person->getName(),
                'email' => $person->getDefaultEmailAddress(),
                'company_name' => $companyName,
                'phone_number' => $person->getDefaultPhone()
            ];
        }
        
        return null;
    }

    /**
     * @param  array  $filters
     * @param  string  $extension
     * @param  null  $fileName
     * @param  null  $path
     */
    public function generateExportFile(array $filters, $extension = 'xlsx', $fileName = null, $path = null)
    {
        ini_set('max_execution_time', 3600);

        if (is_null($fileName)) {
            $fileName = 'person_'.date('YmdHis');
        }

        $personData = $this->personRepository->export($filters);

        $headings = array_keys($personData[0] ?? []);

        $s3Links = ExcelExport::getS3LinksForAllPhotos($personData);

        $exportExcelFiles = ExcelExport::parseFiles($personData, $s3Links);
        $exportExcel = new ExportExcelSource($fileName, $personData, $headings);

        if ($exportExcelFiles) {
            $exportExcel->addFiles($exportExcelFiles);
        }

        $extension = trim($extension, '.');
        $fileName = $fileName . '.' . $extension;
        
        if ($path) {
            return ExcelExport::store($exportExcel, $path . $fileName, $extension);
        } else {
            return ExcelExport::download($exportExcel, $fileName, $extension);
        }
    }
}
