<?php

namespace App\Modules\WorkOrder\Fleetmatics;

use App\Modules\ExternalServices\Common\Http\Client as BaseClient;

class Client extends BaseClient
{
    /**
     * @return string
     */
    public function getLoginPage()
    {
        return $this->get('https://reveal.us.fleetmatics.com/login.aspx');
    }

    /**
     * @param $body
     * @return string
     */
    public function postLogin($body)
    {
        return $this->post('https://reveal.us.fleetmatics.com/login.aspx', $body);
    }

    /**
     * @param $url
     * @return string
     */
    public function getReport($url)
    {
        return $this->get($url);
    }
}
