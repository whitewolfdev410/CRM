<?php

namespace App\Jobs;

use App\Core\Exceptions\JobAlreadyQueuedException;
use App\Modules\ExternalServices\Common\DevErrorLogger;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Contracts\Queue\ShouldQueue;
use LogicException;

/**
 * Base class for queued trackable jobs.
 *
 * Once queued, a job status can be retrieved via API.
 * Class QueuedJobManager should be used for queuing and retrieving jobs.
 *
 * Derived classes should:
 *
 * - define $jobName or getJobName() - job name is a form of categorization of jobs,
 *   it should reflect type of the job (eq. "upload_files")
 *   and can be also used to identify duplicated jobs (eg. "send_invoice_1234").
 *   It supports simple templating, e.g. "send_invoice_{invoiceId}"
 *   - the "{invoiceId}" part will be replaced with $invoiceId property (public or protected)
 *
 * - call jobSuccess() or jobFail() - it is necessary to mark the job as completed,
 *   pass a short feedback message for the user as $feedback
 *   and optional JSON serializable $data storing additional information
 */
abstract class QueuedJob extends Job implements ShouldQueue
{
    protected $jobRecordId;

    /**
     * Job name
     * @var string
     */
    protected $jobName;

    /**
     * Service name
     * @var string|null
     */
    protected $serviceName;

    /**
     * Whether to prevent duplicate
     * @var bool
     */
    protected $preventDuplicate = false;

    private $jobQueued = false;

    private $jobRecordData;

    /**
     * Get job name
     * @return string
     */
    protected function getJobName()
    {
        return preg_replace_callback('/\{(.*?)\}/', function ($m) {
            return $this->{$m[1]};
        }, $this->jobName);
    }

    /**
     * Get related table name (optional)
     * @return string|null
     */
    protected function getJobRelatedTableName()
    {
    }

    /**
     * Get related record ID (optional)
     * @return int|null
     */
    protected function getJobRelatedRecordId()
    {
    }

    /**
     * Set additional data
     * @param mixed $data
     * @return void
     */
    protected function setJobData($data)
    {
        $this->jobRecordData = $data;
    }

    /**
     * Queue job
     *
     * @param  Queue $queue
     *
     * @return mixed
     *
     * @throws \Illuminate\Foundation\Application
     * @throws \InvalidArgumentException
     */
    public function queue(Queue $queue)
    {
        if ($this->shouldPreventDuplicate()) {
            if ($this->isDuplicateQueued()) {
                $this->duplicateAlreadyQueued();
            }
        }

        $this->insertJobRecord();

        $this->jobQueued = true;

        $result = $this->pushToQueue($queue);

        $this->jobQueued();

        return $result;
    }

    /**
     * Push the command onto the given queue instance.
     *
     * @param  \Illuminate\Contracts\Queue\Queue $queue
     * @return mixed
     */
    protected function pushToQueue($queue)
    {
        if (isset($this->queue)) {
            return $queue->pushOn($this->queue, $this);
        }

        return $queue->push($this);
    }

    /**
     * Whether the job has been queued
     *
     * @return bool
     */
    public function isJobQueued()
    {
        return $this->jobQueued;
    }

    /**
     * Called when job is being retried (requeue / rerunNow)
     * @param  int $jobRecordId
     * @return void
     */
    public function retrying($jobRecordId)
    {
    }

    /**
     * Job failure callback
     *
     * @param $data
     */
    public function failed($data)
    {
        app(DevErrorLogger::class)->logGeneralError('Queued job unexpected error', ['queued_job_id' => $this->jobRecordId, 'data' => $data]);

        $this->jobFail('Unexpected error');
    }

    /**
     * Whether to prevent duplicate
     * @return bool
     */
    protected function shouldPreventDuplicate()
    {
        return $this->preventDuplicate;
    }

    /**
     * Whether job with the same name is already queued
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     */
    public function isDuplicateQueued()
    {
        return (bool)$this->findQueuedDuplicate();
    }

    /**
     * Find queued job with the same name
     *
     * @return \Illuminate\Database\Query\Builder|null|Object
     *
     * @throws \InvalidArgumentException
     */
    public function findQueuedDuplicate()
    {
        return $this->getQueuedJobTable()
            ->where('name', '=', $this->getJobName())
            ->where(function ($query) {
                $query
                    ->whereNull('completed_at')
                    ->orWhere('completed_at', '=', '0000-00-00 00:00:00');
            })
            ->first();
    }

    /**
     * Called if duplicate is already queued. Throw an exception by default
     *
     * @return void
     *
     * @throws \Illuminate\Foundation\Application
     */
    protected function duplicateAlreadyQueued()
    {
        throw app(JobAlreadyQueuedException::class);
    }

    /**
     * Insert new job record and set its ID
     * @return void
     */
    protected function insertJobRecord()
    {
        $table = $this->getQueuedJobTable();
        $attributes = $this->getJobRecordAttributes();

        $this->jobRecordId = $table->insertGetId($attributes);
    }

    /**
     * Called after job was queued
     * @return void
     */
    protected function jobQueued()
    {
    }

    /**
     * Called after job was completed
     *
     * @param  bool $success
     * @param  string $feedback
     * @param  mixed $data
     *
     * @return void
     */
    protected function jobCompleted($success, $feedback, $data)
    {
    }

    /**
     * Get queued job table
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getQueuedJobTable()
    {
        return app('db')->table('queued_job');
    }

    /**
     * Get new job record attributes
     * @return array
     */
    protected function getJobRecordAttributes()
    {
        $attributes = [
            'name' => $this->getJobName(),
            'queued_at' => Carbon::now(),
            'table_name' => $this->getJobRelatedTableName(),
            'record_id' => $this->getJobRelatedRecordId(),
            'data' => json_encode(isset($this->jobRecordData) ? $this->jobRecordData : null),
        ];

        if ($this->storesJobPayload()) {
            $attributes['job_payload'] = $this->createJobPayload();
        }

        return $attributes;
    }

    /**
     * Whether job payload should be stored
     * @return bool
     */
    private function storesJobPayload()
    {
        return config('queue.queued_job.store_payload') !== false;
    }

    /**
     * Create job payload - serialized job class
     * @return string
     */
    protected function createJobPayload()
    {
        return serialize(clone $this);
    }

    /**
     * Called before object serialization
     * @return void
     */
    protected function serializing()
    {
    }

    /**
     * Clean big data before object serialization
     * @return void
     */
    public function __clone()
    {
        $this->jobRecordData = null;

        $this->serializing();
    }

    /**
     * Update job record
     *
     * @param $success
     * @param $feedback
     * @param $data
     *
     * @throws \InvalidArgumentException
     */
    protected function completeJob($success, $feedback, $data)
    {
        if (empty($this->jobRecordId)) {
            $this->insertJobRecord();
        }

        $attributes = [
            'success' => $success,
            'completed_at' => Carbon::now(),
            'feedback' => $feedback,
            'service' => $this->serviceName,
        ];

        if ($data) {
            $attributes['data'] = json_encode($data);
        }

        $table = $this->getQueuedJobTable();

        $table->where('id', '=', $this->jobRecordId)->update($attributes);

        $this->jobCompleted($success, $feedback, $data);
    }

    /**
     * Mark job as complete
     *
     * @param  string $feedback
     * @param  mixed $data
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function jobSuccess($feedback, $data = null)
    {
        $this->completeJob(true, $feedback, $data);
    }

    /**
     * Mark job as failed
     *
     * @param  string $feedback
     * @param  mixed $data
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function jobFail($feedback, $data = null)
    {
        $this->completeJob(false, $feedback, $data);
    }

    /**
     * Get tracking URL
     *
     * @return string
     *
     * @throws \LogicException
     */
    public function getTrackingUrl()
    {
        return route('get-queued-job', ['id' => $this->getTrackingId()]);
    }

    /**
     * Get tracking ID
     *
     * @return int
     *
     * @throws \LogicException
     */
    public function getTrackingId()
    {
        if (empty($this->jobRecordId)) {
            throw new LogicException('Job is not queued properly and cannot be tracked');
        }

        return $this->jobRecordId;
    }

    /**
     * Get job record ID and force inserting to database if its empty
     * @return int
     */
    public function getForceJobRecordId()
    {
        if (empty($this->jobRecordId)) {
            $this->insertJobRecord();
        }

        return $this->jobRecordId;
    }

    /**
     * Get job record
     *
     * @return array|false
     */
    public function getJobRecord()
    {
        //if id is empty then return false
        if (empty($this->jobRecordId)) {
            return false;
        } else { //otherwise get and return record
            $table = $this->getQueuedJobTable();

            return (array) $table->find($this->jobRecordId);
        }
    }

    /**
     * Get job record data
     * @return mixed
     */
    public function getJobRecordData()
    {
        if (!isset($this->jobRecordData)) {
            $data = $this->getQueuedJobTable()
                ->where('id', $this->getForceJobRecordId())
                ->value('data');

            $this->jobRecordData = json_decode($data, true) ?: [];
        }

        return $this->jobRecordData;
    }

    /**
     * Update job record data
     * @param  mixed $data
     * @return void
     */
    protected function updateJobRecordData($data)
    {
        $this->jobRecordData = $data;

        $this->getQueuedJobTable()
            ->where('id', $this->getForceJobRecordId())
            ->update(['data' => json_encode($data)]);
    }
    
    /**
     * Get job record request data
     * @return mixed
     */
    public function getJobRecordRequestData()
    {
        $data = $this->getQueuedJobTable()
                ->where('id', $this->getForceJobRecordId())
                ->value('request_data');

        return json_decode($data, true) ?: [];
    }
    
    
    /**
     * Merge and update job record data
     * @param  array  $data
     * @return void
     */
    protected function mergeJobRecordData(array $data)
    {
        $data = array_replace_recursive($this->getJobRecordData(), $data);

        $this->updateJobRecordData($data);
    }
}
