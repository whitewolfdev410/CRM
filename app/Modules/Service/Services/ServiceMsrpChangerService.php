<?php

namespace App\Modules\Service\Services;

use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\PricingStructure\Models\PricingMatrix;
use App\Modules\PricingStructure\Models\PricingStructure;
use App\Modules\PricingStructure\Repositories\PricingMatrixRepository;
use App\Modules\PricingStructure\Repositories\PricingStructureRepository;
use App\Modules\PricingStructure\Services\PricingMatrixService;
use App\Modules\Service\Models\Service;
use App\Modules\WorkOrder\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection;

class ServiceMsrpChangerService
{
    /**
     * @var PersonRepository
     */
    protected $personRepo;

    /**
     * @var PricingMatrixService|mixed
     */
    protected $pricingMatrixService;
    /**
     * @var PricingStructureRepository
     */
    protected $psRepo;
    /**
     * @var PricingMatrixRepository
     */
    protected $psMatrixRepo;

    /**
     * Initialize class
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->personRepo = $this->app->make(PersonRepository::class);
        $this->psRepo = $this->app->make(PricingStructureRepository::class);
        $this->psMatrixRepo = $this->app->make(PricingMatrixRepository::class);
        $this->pricingMatrixService = $this->app->make(PricingMatrixService::class);
    }

    /**
     * Modify Msrp for services collection
     *
     * @param Collection $services
     * @param WorkOrder  $wo
     * @param Carbon     $date
     */
    public function modifyMultiple(
        $services,
        WorkOrder $wo,
        Carbon $date = null
    ) {
        // no services
        if (!count($services)) {
            return;
        }

        // this method works like work_order::getServicePriceByWorkOrderID but it
        // works for collection of items so some data are preloaded before loop

        // get billing company pricing structure
        $bcId = $wo->getBillingCompanyPersonId();
        $bcStructure = null;
        if (!empty($bcId)) {
            $bcStructure = $this->getPricingStructureByPersonId($bcId);
            if (empty($bcStructure)) {
                if ($bName = $this->getPersonName($bcId)) {
                    $bcStructure = $this->getPricingStructure($bName);
                }
            }
        }

        // get company pricing structure
        $cId = $wo->getCompanyPersonId();
        $cStructure = null;
        if (!empty($cId)) {
            $cStructure = $this->getPricingStructureByPersonId($cId);
            if (empty($cStructure)) {
                if ($cName = $this->getPersonName($cId)) {
                    $cStructure = $this->getPricingStructure($cName);
                }
            }
        }

        $pricing = $this->pricingMatrixService->getMatrixForStructures($bcStructure, $cStructure, $date);
        foreach ($services as $service) {
            $service->msrp = isset($pricing['services'][$service->getId()])
                ? (float)$pricing['services'][$service->getId()]
                : 0;

            $service->price = (float)$service->msrp;
            $service->min_qty = isset($pricing['services_min_qty'][$service->getId()])
                ? (float)$pricing['services_min_qty'][$service->getId()]
                : 0;
        }
    }

    /**
     * Modify Msrp for items collection
     *
     * @param             $services
     * @param null        $billingCompanyPersonId
     * @param null        $companyPersonId
     * @param Carbon|null $date
     */
    public function modifyMultipleForCompany(
        $services,
        $billingCompanyPersonId = null,
        $companyPersonId = null,
        Carbon $date = null
    ) {
        // no items
        if (!count($services)) {
            return;
        }

        // this method works like work_order::getItemPriceByWorkOrderID but it
        // works for collection of items so some data are preloaded before loop

        // get billing company pricing structure
        $bcStructure = null;
        if (!empty($billingCompanyPersonId)) {
            $bcStructure = $this->getPricingStructureByPersonId($billingCompanyPersonId);
            if (empty($bcStructure)) {
                if ($bName = $this->getPersonName($billingCompanyPersonId)) {
                    $bcStructure = $this->getPricingStructure($bName);
                }
            }
        }

        // get company pricing structure
        $cStructure = null;
        if (!empty($companyPersonId)) {
            $cStructure = $this->getPricingStructureByPersonId($companyPersonId);
            if (empty($cStructure)) {
                if ($cName = $this->getPersonName($companyPersonId)) {
                    $cStructure = $this->getPricingStructure($cName);
                }
            }
        }

        $pricing = $this->pricingMatrixService->getMatrixForStructures($bcStructure, $cStructure, $date);
        foreach ($services as $service) {
            $service->msrp = isset($pricing['services'][$service->getId()])
                ? (float)$pricing['services'][$service->getId()]
                : 0;
            
            $service->price = (float)$service->msrp;
            $service->min_qty = isset($pricing['services_min_qty'][$service->getId()])
                ? (float)$pricing['services_min_qty'][$service->getId()]
                : 0;
        }
    }

    /**
     * Get valid msrp for service, given pricing structures and date
     *
     * @param Service          $service
     * @param PricingStructure $bcStructure
     * @param PricingStructure $cStructure
     * @param Carbon           $date
     * @param                  $verbose = false
     *
     * @return mixed|null
     */
    public function getMsrp(
        Service $service,
        PricingStructure $bcStructure = null,
        PricingStructure $cStructure = null,
        Carbon $date = null,
        $verbose = false
    ) {
        if ($bcStructure) {
            $price = $this->getMsrpByPricingStructure(
                $service,
                $bcStructure,
                $date,
                $verbose
            );

            if ($price) {
                return $price;
            }
        }

        if ($cStructure) {
            $price = $this->getMsrpByPricingStructure(
                $service,
                $cStructure,
                $date,
                $verbose
            );

            if ($price) {
                return $price;
            }
        }

        $price = $this->getSimpleServicePrice($service);
        return $price;
    }

    /**
     * Return service MSRP by pricing structure
     *
     * @param Service          $service
     * @param PricingStructure $pricingStructure
     * @param Carbon           $date
     * @param boolean          $verbose
     *
     * @return float|null
     */
    protected function getMsrpByPricingStructure(
        Service $service,
        PricingStructure $pricingStructure,
        Carbon $date = null,
        $verbose = false
    ) {
        if (empty($date)) {
            $price = $this->getServicePrice(
                $service,
                $pricingStructure->getId()
            );

            if ($verbose) {
                echo "Empty date - getMsrpByPricingStructure()\n";
                print_r($price);
            }

            if ($price) {
                return $price;
            }
        } else {
            $holidays = $this->getHolidays($date);

            if (isset($holidays)
                && in_array($date->format('Y-m-d'), $holidays)
            ) {
                $price = $this->getHolidayPrice(
                    $service,
                    $pricingStructure->getId(),
                    $price
                );

                if ($verbose) {
                    echo "Holidays - getMsrpByPricingStructure()\n";
                    print_r($price);
                }

                if ($price) {
                    return $price;
                }
            }
        }

        $matrixes = $this->getMatrixesAndOptions(
            $service,
            $pricingStructure
        );

        $price = $this->getPriceFromMatrixes($matrixes, $date, null);

        if ($verbose) {
            echo "Matrixes - getMsrpByPricingStructure()\n";
            print_r($price);
        }

        if ($price) {
            return $price;
        }

        return null;
    }

    /**
     * Get price based on given matrixes and date
     *
     * @param array  $matrixes
     * @param Carbon $date
     * @param mixed  $price
     *
     * @return mixed
     */
    protected function getPriceFromMatrixes(
        array $matrixes,
        Carbon $date,
        $price
    ) {
        if ($date === null || empty($matrixes)) {
            return $price;
        }

        $timeSheetHour = $date->format('H');
        $timeSheetDay = $date->format('d');
        $timeSheetMonth = $date->format('m');
        $timeSheetWeekDay = $date->format('N');

        /** @var PricingMatrix $matrix */
        foreach ($matrixes as $matrix) {
            $opt = $matrix->options;
            if (!$opt) {
                continue;
            }

            if ((int)$opt->time_to_start_hour < (int)$opt->time_to_finish_hour) {
                if (in_array($timeSheetHour, range($opt->time_to_start_hour, $opt->time_to_finish_hour))) {
                    if (in_array($timeSheetWeekDay, range($opt->week_day_start, $opt->week_day_finish))) {
                        if (in_array($timeSheetDay, range($opt->month_day_start, $opt->month_day_finish))) {
                            if (in_array($timeSheetMonth, range($opt->month_start, $opt->month_finish))) {
                                return $matrix->getPrice();
                            }
                        }
                    }
                }
            } elseif (in_array($timeSheetHour, range(0, ($opt->time_to_finish_hour - 1)))
                || in_array($timeSheetHour, range($opt->time_to_start_hour, 24))
            ) {
                if (in_array($timeSheetWeekDay, range($opt->week_day_start, $opt->week_day_finish))) {
                    if (in_array($timeSheetDay, range($opt->month_day_start, $opt->month_day_finish))) {
                        if (in_array($timeSheetMonth, range($opt->month_start, $opt->month_finish))) {
                            return $matrix->getPrice();
                        }
                    }
                }
            }
        }

        return $price;
    }


    /**
     * Get pricing matrixes with options for matching tariffs
     *
     * @param Service          $service
     * @param PricingStructure $ps
     *
     * @return array
     */
    protected function getMatrixesAndOptions(
        Service $service,
        PricingStructure $ps
    ) {
        $tariffs = $ps->tariffs;
        $tariffsIds = $tariffs->pluck('pricing_structure_id')->all();

        /* make array with [tariffId => [Tariff], ...] structure to match later
           tariffs without filtering and run only one query for all tariffs
           instead of many queries for each tariff as it used to be
        */
        $tariffs = $tariffs->groupBy('pricing_structure_id');

        $matrixes = $this->psMatrixRepo->findForServiceAndPs(
            $service->getId(),
            $tariffsIds
        );

        $outMatrixes = [];
        /** @var PricingMatrix $matrix */
        foreach ($matrixes as $matrix) {
            if ($matrix->getPrice() > 0) {
                $tariffId = $matrix->getPricingStructureId();

                $matrix->options
                    = json_decode($tariffs[$tariffId][0]->getOptions());
                $outMatrixes[] = $matrix;
            }
        }

        return $outMatrixes;
    }

    /**
     * Get holiday price for service if it exists otherwise returns given price
     *
     * @param Service $service
     * @param int     $pricingStructureId
     * @param mixed   $price
     *
     * @return mixed
     */
    protected function getHolidayPrice(
        Service $service,
        $pricingStructureId,
        $price
    ) {
        /** @var PricingMatrix $matrix */
        $matrix = $this->psMatrixRepo->findForServiceAndHoliday(
            $service->getId(),
            $pricingStructureId
        );

        if ($matrix) {
            $price = $matrix->getPrice();
        }

        return $price;
    }

    /**
     * Get holiday settings
     *
     * @param Carbon $date
     *
     * @return array
     */
    protected function getHolidays(Carbon $date = null)
    {
        if ($date === null) {
            return [];
        }

        return $this->app->config->get(
            'crm_settings.work_order.national_holidays.'
            . $date->format('Y'),
            []
        );
    }

    /**
     * Get service price based on given structure or service price
     *
     * @param Service $service
     * @param int     $pricingStructureId
     *
     * @return float
     */
    protected function getServicePrice(Service $service, $pricingStructureId)
    {
        /** @var PricingMatrix $matrix */
        $matrix = $this->psMatrixRepo->findForServiceAndPs(
            $service->getId(),
            $pricingStructureId
        );
        if ($matrix && $matrix->getPrice() > 0) {
            return $matrix->getPrice();
        }

        return $this->getSimpleServicePrice($service);
    }

    /**
     * Get service price
     *
     * @param Service $service
     *
     * @return float
     */
    protected function getSimpleServicePrice(Service $service)
    {
        $defaultPricingStructureId = config(
            'crm_settings.default_pricing_structure_id',
            1
        );

        $matrix = $this->psMatrixRepo->findForServiceAndPs(
            $service->getId(),
            $defaultPricingStructureId
        );

        if ($matrix && $matrix->getPrice() > 0) {
            return $matrix->getPrice();
        }

        return $service->getMsrp();
    }

    /**
     * Get person name
     *
     * @param int $personId
     *
     * @return null|string
     */
    protected function getPersonName($personId)
    {
        return $this->personRepo->getPersonName($personId);
    }

    /**
     * Get first pricing structure with given name
     *
     * @param string $name
     *
     * @return PricingStructure
     */
    protected function getPricingStructure($name)
    {
        return $this->psRepo->findFirstByName($name);
    }

    /**
     * Get first pricing structure by person id
     *
     * @param $personId
     *
     * @return PricingStructure
     */
    protected function getPricingStructureByPersonId($personId)
    {
        return $this->psRepo->findFirstByPersonId($personId);
    }
}
