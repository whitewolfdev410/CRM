<?php


namespace App\Core;

use App\Helpers\ExcelExport;
use App\Helpers\Exports\ExportExcelFromArray;
use App\Modules\File\Services\FileService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\DB;

class ExportService
{
    /**
     * @var FileService
     */
    private $fileService;
    /**
     * @var Container
     */
    private $app;

    /**
     * Initialize class parameters
     *
     * @param Container $app
     * @param FileService $fileService
     */
    public function __construct(
        Container $app,
        FileService $fileService
    ) {
        $this->app = $app;
        $this->fileService = $fileService;
    }
    
    public function generateExport($data)
    {
        $fileName = $data['table_name'].'_export_'.time();
        
        $models = DB::select($data['data']);

        $models = array_map(function ($value) {
            return (array)$value;
        }, $models);

        $type = ucfirst($data['export_type']);
        
        $file = ExcelExport::raw(new ExportExcelFromArray($fileName, $models), $type);
        
        $this->fileService->saveFromContent(
            $file,
            $fileName.'.'.$data['export_type'],
            '',
            $data['table_name'],
            0,
            '',
            $data['person_id'],
            getTypeIdByKey('file.export')
        );
    }

    /**
     * Starts this job
     *
     * @param Job $job
     * @param array $data
     */
    public function fire($job, $data)
    {
        $this->generateExport($data);
        $job->delete();
    }
}
