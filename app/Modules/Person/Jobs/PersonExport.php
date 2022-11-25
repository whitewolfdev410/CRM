<?php

namespace App\Modules\Person\Jobs;

use App\Jobs\QueuedJob;
use App\Modules\ExternalServices\Common\DevErrorLogger;
use App\Modules\File\Services\FileService;
use App\Modules\Person\Services\PersonService;
use Carbon\Carbon;

class PersonExport extends QueuedJob
{
    protected $jobName = 'person.export';

    /**
     * {@inheritdoc}
     */
    protected function getJobRelatedTableName()
    {
        return 'person';
    }

    /**
     * @var array
     */
    private $filters;

    /**
     * Job failure callback
     *
     * @param $data
     */
    public function failed($data)
    {
        app(DevErrorLogger::class)
            ->logGeneralError('Queued job unexpected error', [
                'data'  => $data,
                'debug' => (new \Exception)->getTraceAsString()
            ]);

        $this->jobFail('Unexpected error');
    }

    /**
     * Constructor
     *
     * @param $filters
     */
    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    /**
     * Perform the job.
     *
     * Add an activity on failure
     *
     * @param  PersonService  $personService
     *
     * @return void
     * @throws \Exception
     */
    public function handle(PersonService $personService)
    {
        $queued_job_id = $this->getForceJobRecordId();

        // path and filename
        $extension = '.xlsx';
        $path = storage_path('exports'.DIRECTORY_SEPARATOR.app()->environment().DIRECTORY_SEPARATOR);
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        do {
            $fileName = 'person_'.Carbon::now()->toDateString().'_'.rand(1000, 9999);
        } while (file_exists($path.$fileName.$extension));

        $personService->generateExportFile($this->filters, $extension, $fileName, $path);

        // save in DB
        $fileService = app(FileService::class);
        $fileService->saveFromLocal(
            $path.$fileName.$extension,
            'Person Export '.Carbon::now()->toDateTimeString(),
            'queued_job',
            $queued_job_id
        );

        $this->jobSuccess('Person export has been done');
    }
}
