<?php

namespace App\Jobs;

use Exception;

/**
 * Runs multiple queued jobs at once
 */
class MultipleQueuedJob extends QueuedJob
{
    private $jobs;

    /**
     * Constructor
     * @param $jobName
     * @param array $jobs
     */
    public function __construct($jobName, array $jobs)
    {
        $this->jobName = $jobName;
        $this->jobs = $jobs;
    }

    /**
     * Run job
     * @param  QueuedJobManager $manager
     * @return void
     */
    public function handle(QueuedJobManager $manager)
    {
        $jobsCount = count($this->jobs);

        // first, insert jobs as 'queued'
        foreach ($this->jobs as $job) {
            $job->getForceJobRecordId();
        }

        // then execute each job
        foreach ($this->jobs as $job) {
            try {
                $manager->runNow($job);
            } catch (Exception $e) {
                $job->failed(null);
            }
        }

        $this->jobSuccess("Completed {$jobsCount} jobs");
    }

    /**
     * Job failure callback
     *
     * @param $data
     */
    public function failed($data)
    {
        foreach ($this->jobs as $job) {
            $job->failed(null);
        }

        parent::failed($data);
    }
}
