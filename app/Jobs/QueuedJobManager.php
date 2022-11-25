<?php

namespace App\Jobs;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use App\Modules\ExternalServices\Common\DevErrorLogger;

class QueuedJobManager
{
    protected $app;

    /**
     * Constructor
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Queue job and return tracking information
     * @param  QueuedJob $job
     * @return array
     */
    public function queue(QueuedJob $job)
    {
        $this->app[Dispatcher::class]->dispatchToQueue($job);

        return [
            'tracking_url' => $job->getTrackingUrl(),
            'tracking_id' => $job->getTrackingId(),
        ];
    }

    /**
     * Whether duplicate job is already queued
     * @param  QueuedJob $job
     * @return bool
     */
    public function isQueued(QueuedJob $job)
    {
        return $job->isDuplicateQueued();
    }

    /**
     * Run the job now and return result information
     * @param  QueuedJob $job
     * @return array result record
     */
    public function runNow(QueuedJob $job)
    {
        $this->app[Dispatcher::class]->dispatchNow($job);

        return $this->get($job->getTrackingId());
    }

    /**
     * Get queued job information by ID
     * @param  mixed $id
     * @return array
     */
    public function get($id)
    {
        $record = $this->app['db']->table('queued_job')->find($id);

        if (!$record) {
            return null;
        }

        $record->data = json_decode($record->data, true);

        return (array) $record;
    }

    /**
     * Requeue job by ID
     * @param  mixed $id
     * @return array|null
     */
    public function requeue($id)
    {
        $job = $this->findJobInstance($id);

        if ($job) {
            $job->retrying($id);

            return $this->queue($job);
        }
    }

    /**
     * Rerun job by ID
     * @param  mixed $id
     * @return array|null
     */
    public function rerunNow($id)
    {
        $job = $this->findJobInstance($id);

        if ($job) {
            $job->retrying($id);
            
            return $this->runNow($job);
        }
    }

    /**
     * Find job instance by ID
     * @param  mixed $id
     * @return QueuedJob|null
     */
    public function findJobInstance($id)
    {
        $jobPayload = $record = $this->app['db']
            ->table('queued_job')
            ->where('id', $id)
            ->value('job_payload');

        if ($jobPayload) {
            return unserialize($jobPayload);
        }
    }

    /**
     * Run nested job now - this method should be called from within another job
     * @param  QueuedJob $job
     * @return array
     */
    public function runNowNested(QueuedJob $job)
    {
        // save current context of the error logger
        // and restore it after the job is done
        $logger = $this->app[DevErrorLogger::class];

        $loggerContext = $logger->getCurrentContext();

        try {
            return $this->runNow($job);
        } finally {
            $logger->setCurrentContext($loggerContext);
        }
    }
}
