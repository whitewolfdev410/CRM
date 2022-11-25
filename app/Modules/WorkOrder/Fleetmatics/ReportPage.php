<?php
//WebDriver timeout set to 10 min. Accepted by Rafal at 29-05-2017.
//Timeout used to wait for generate csv report from Fleetmatics.
//It is required to use short periods. Tested for a 5 days period.

namespace App\Modules\WorkOrder\Fleetmatics;

use App\Modules\ExternalServices\Common\WebDriver\BasePage;
use Facebook\WebDriver\Exception\ElementNotVisibleException;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

class ReportPage extends BasePage
{

    /**
     * @param $dateFrom
     * @param $dateTo
     * @return string
     */
    public function generateReportUrl($dateFrom, $dateTo)
    {
        $this->openReportPage();
        $this->switchFrame();
        $this->selectReportDateRange($dateFrom, $dateTo);
        $this->selectReportData();
        $url = $this->getReportUrl();

        return $url;
    }

    /**
     * Call wait() on the driver.
     * Set timeout to 10 min to generate complete csv report
     * @return WebDriverWait
     */
    public function wait()
    {
        return $this->driver->wait(600);
    }

    /**
     * @return $this
     */
    private function openReportPage()
    {
        $this->openUrl('https://reveal.us.fleetmatics.com/report/');

        $this->driver->wait()->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::xpath("//a[@data-webtrends-meta='gallery_open-report_Daily-Report']")));
        $this->driver->findElement(WebDriverBy::xpath("//a[@data-webtrends-meta='gallery_open-report_Daily-Report']"))->click();

        return $this;
    }
    /**
     * @return $this
     */
    private function switchFrame()
    {
        $openTabs = $this->driver->findElements(WebDriverBy::xpath("//a[contains(@id, 'divTab_a_')]"));
        $this->switchToFrame('ifmRep_'.count($openTabs));

        return $this;
    }

    /**
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     * @return $this
     */
    private function selectReportDateRange(\DateTime $dateFrom, \DateTime $dateTo)
    {
        $startYear = $dateFrom->format('Y');
        $startMonth = $dateFrom->format('M');
        $startDay = $dateFrom->format('j');
        $startSuffixDay = strtolower($dateFrom->format('D'));

        $stopYear = $dateTo->format('Y');
        $stopMonth = $dateTo->format('M');
        $stopDay = $dateTo->format('j');
        $stopSuffixDay = strtolower($dateTo->format('D'));

        //Select report date range
        $this->driver->wait()->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::xpath("//select[@id='FuzzyDateSelection']/option[text()='Other']")));
        $this->driver->findElement(WebDriverBy::xpath("//select[@id='FuzzyDateSelection']/option[text()='Other']"))->click();

        //Select start date
        $this->driver->findElement(WebDriverBy::id('ReportStartTimeUTC'))->click();
        $this->sleep(2);
        $this->driver->findElement(WebDriverBy::xpath("//a[contains(@class, 'jquery-dtpckr-current')]"))->click();
        $this->driver->findElement(WebDriverBy::xpath("//a[contains(@class, 'jquery-dtpckr-current')]"))->click();
        $this->driver->findElement(WebDriverBy::xpath("//td[contains(@class, 'jquery-dtpckr-year')  and contains(text(), '{$startYear}')]"))->click();
        $this->driver->findElement(WebDriverBy::xpath("//td[contains(@class, 'jquery-dtpckr-month')  and contains(text(), '{$startMonth}')]"))->click();
        $this->driver->findElement(WebDriverBy::xpath("//td[contains(@class, 'jquery-dtpckr-day-{$startSuffixDay}') and text()='{$startDay}']"))->click();

        //Select stop date
        $this->driver->findElement(WebDriverBy::id('ReportEndTimeUTC'))->click();
        $this->sleep(2);
        //need to select second element
        $this->driver->findElement(WebDriverBy::xpath("(//a[contains(@class, 'jquery-dtpckr-current')])[2]"))->click();
        $this->driver->findElement(WebDriverBy::xpath("(//a[contains(@class, 'jquery-dtpckr-current')])[2]"))->click();
        $this->driver->findElement(WebDriverBy::xpath("//td[contains(@class, 'jquery-dtpckr-year')  and contains(text(), '{$stopYear}')]"))->click();
        $this->driver->findElement(WebDriverBy::xpath("//td[contains(@class, 'jquery-dtpckr-month')  and contains(text(), '{$stopMonth}')]"))->click();
        try {
            $this->driver->findElement(WebDriverBy::xpath("//td[contains(@class, 'jquery-dtpckr-day-{$stopSuffixDay}') and text()='{$stopDay}']"))->click();
        } catch (ElementNotVisibleException $e) {
            $this->driver->findElement(WebDriverBy::xpath("(//td[contains(@class, 'jquery-dtpckr-day-{$stopSuffixDay}') and text()='{$stopDay}'])[2]"))->click();
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function selectReportData()
    {
        //uncheck ' My Entire Fleet'
        if ($this->driver->findElement(WebDriverBy::id('All'))->isSelected()) {
            $this->driver->findElement(WebDriverBy::id('All'))->click();
        }

        $this->driver->wait()->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('AddVehicles')));
        $this->sleep(2);

        //select all vehicles
        $this->driver->findElement(WebDriverBy::id('AddVehicles'))->click();
        $this->sleep(2);
        $this->driver->findElement(WebDriverBy::id('advTreeSelectAll'))->click();
        $this->driver->findElement(WebDriverBy::id('saveGroupSelection'))->click();

        //select all groups
        $this->driver->findElement(WebDriverBy::id('AddGroups'))->click();
        $this->sleep(2);
        $this->driver->findElement(WebDriverBy::id('advTreeSelectAll'))->click();
        $this->driver->findElement(WebDriverBy::id('saveGroupSelection'))->click();

        return $this;
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function getReportUrl()
    {
        $this->driver->findElement(WebDriverBy::id('btnRunReport'))->click();

        $this->wait()->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('linkCSV')));

        //need wait for complete report url. Timeout set to 10 min
        $loopCounter = 0;
        while (str_contains_any($this->driver->findElement(WebDriverBy::id('linkCSV'))->getAttribute('class'), 'inprogress')) {
            if ($loopCounter > 60) {
                throw new \Exception('Unable to get report url');
            }
            $this->sleep(10);
            $loopCounter++;
        }

        $this->driver->findElement(WebDriverBy::id('linkCSV'))->click();

        $this->switchToLatestWindow();

        return $this->driver->getCurrentURL();
    }
}
