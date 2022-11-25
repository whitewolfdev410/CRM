<?php

namespace App\Modules\WorkOrder\Fleetmatics;

use App\Modules\ExternalServices\Common\FormParser;
use App\Modules\ExternalServices\Common\WebDriver\PageFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

class ReportSaver
{
    /**
     * @var
     */
    private $pageFactory;

    /**
     * @var
     */
    private $page;

    /**
     * @var
     */
    private $config;

    /**
     * @var Container
     */
    private $app;

    /**
     * @var
     */
    private $httpClient;

    /**
     * ReportSaver constructor.
     * @param Container $app
     * @param PageFactory $pageFactory
     */
    public function __construct(Container $app, PageFactory $pageFactory)
    {
        $this->app = $app;
        $this->pageFactory = $app[PageFactory::class];
        $this->config = $this->app['config']['external_services.services.Fleetmatics'];
    }

    /**
     * Save report from Fleetmatics service
     * @return string. Report path
     */
    public function save($dateFrom, $dateTo)
    {
        try {
            $url = $this->getReportUrl($dateFrom, $dateTo);
            $this->login();

            return $this->saveReport($url);
        } catch (\Exception $e) {
            Log::error('Unable to save report', ['exception' => $e]);
        }

        return null;
    }

    /**
     * @return string
     */
    private function getReportUrl($dateFrom, $dateTo)
    {
        //Login using webDriver
        $this->page = $this->pageFactory
            ->open(LoginPage::class)
            ->login($this->config['username'], $this->config['password']);

        //get report url
        return $this->page->switchPage(ReportPage::class)
            ->generateReportUrl($dateFrom, $dateTo);
    }

    /**
     * Make http login
     */
    private function login()
    {
        $this->httpClient = $this->app[PageFactory::class]->make(Client::class);
        $loginPageContent = $this->httpClient->getLoginPage();

        $data = $this->app[FormParser::class]->getByFormId($loginPageContent, 'form1');

        $data['username'] = $this->config['username'];
        $data['password'] = $this->config['password'];
        unset($data['btnAcceptLicense']);
        unset($data['remember']);

        $this->httpClient->postLogin($data);
    }

    /**
     * @param $url
     * @return string
     */
    private function saveReport($url)
    {
        //change Exporting to ToOutput in url
        $url = str_replace('Exporting', 'ToOutput', $url);
        $dirPath = storage_path('fleetmatics_reports/report'.time().'.csv');

        //save report to file
        try {
            file_put_contents($dirPath, $this->httpClient->getReport($url));
        } catch (\Exception $e) {
            Log::error('Unable to save report', ['path' => $dirPath, 'exception' => $e]);
        }

        return $dirPath;
    }
}
