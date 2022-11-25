<?php

namespace App\Core\Old;

use App\Core\Crm;
use App\Core\Logger;
use Carbon\Carbon;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

class DateConverter
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var Crm
     */
    protected $crm;

    /**
     * @var Logger
     */
    protected $log;

    /**
     * Initialize class
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->crm = $app->make('crm');
        $this->log = $app->make('logger');
    }

    /**
     * Calculate offset (in seconds) from User to UTC timezone
     *
     * @param string $userTimeZone
     *
     * @return int
     */
    protected function getUserFromUtcOffset($userTimeZone)
    {
        if ($userTimeZone == '') {
            $this->log('Empty timezone');

            return 0;
        }

        try {
            return Carbon::now()->setTimezone($userTimeZone)->getOffset();
        } catch (\Exception $e) {
            $this->log('Invalid timezone "' . $userTimeZone . '"');
        }

        return 0;
    }

    /**
     * Get user UTC offset (only if app is set to UTC otherwise 0)
     *
     * @param string $userTimeZone
     *
     * @return int
     */
    public function getUserUtcOffsetIfUtc($userTimeZone)
    {
        if (!$this->crm->isUtc()) {
            return 0;
        }

        return $this->getUserFromUtcOffset($userTimeZone);
    }

    /**
     * Convert date to user timezone if needed and possible to format Y-m-d
     * H:i:s
     *
     * @param $date
     * @param $userTimezone
     *
     * @return string|static
     */
    public function dateToUser($date, $userTimezone)
    {
        return $this->toUser($date, $userTimezone, 'Y-m-d H:i:s');
    }

    /**
     * Convert date to user timezone if needed and possible. If format is given
     * it returns string in specified format otherwise it returns Carbon object
     *
     * @param string|Carbon $date
     * @param string $userTimeZone
     * @param string|null $format Type of date formatting, example: Y-m-d
     *
     * @return Carbon|string|static
     */
    public function toUser($date, $userTimeZone, $format = null)
    {
        if (isEmptyDateTime($date) || !$this->crm->isUtc()) {
            return $date;
        }

        if (!$date instanceof Carbon) {
            $dateObj = new Carbon($date, 'UTC');
        } else {
            $dateObj = clone $date;
        }

        // no user timezone - assume it's UTC
        if ($userTimeZone == '') {
            $this->log('Empty timezone');

            $userTimeZone = 'UTC';
        }

        try {
            $dateObj = $dateObj->setTimezone($userTimeZone);
        } catch (\Exception $e) {
            $this->log('Invalid timezone "' . $userTimeZone . '"');

            return $date;
        }
        if ($format) {
            return $dateObj->format($format);
        }

        return $dateObj;
    }

    /**
     * Log possible error logs to file
     *
     * @param $message
     */
    protected function log($message)
    {
        /** @var Request $request */
        $request = $this->app->request;
        $message .= ' for ' . $request->method() . ' ' . $request->fullUrl();
        $this->log->log($message, 'invalid_timezones_log');
    }
}
