<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\Crm;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Container\Container;

class LinkPersonWoQbInfoService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var LinkPersonWoRepository
     */
    protected $lpWoRepo;

    /**
     * @var Crm
     */
    protected $crm;

    /**
     * LinkPersonWoJobDescriptionService constructor.
     *
     * @param Container $app
     * @param LinkPersonWoRepository $lpWoRepo
     */
    public function __construct(
        Container $app,
        LinkPersonWoRepository $lpWoRepo
    ) {
        $this->app = $app;
        $this->lpWoRepo = $lpWoRepo;
        $this->crm = $this->app->make(Crm::class);
    }

    /**
     * Get modified qb_info value
     *
     * @param LinkPersonWo $lpWo
     * @param WorkOrder $wo
     *
     * @return string
     */
    public function getQbInfo(LinkPersonWo $lpWo, WorkOrder $wo)
    {
        if ($this->crm->is('gfs')) {
            return $this->getGfsQbInfo($wo);
        } elseif ($this->crm->is('clm')) {
            return $this->getClmQbInfo($wo);
        }

        return $this->getDefaultQbInfo($wo);
    }

    /**
     * Get general qb_info (for all installations)
     *
     * @param WorkOrder $wo
     *
     * @return string
     */
    protected function getClmQbInfo(WorkOrder $wo)
    {
        switch ($wo->getCompanyPersonId()) {
            default:
            case $this->crm->getClientId('verizon', 'clm'):
            case $this->crm->getClientId('cvs', 'clm'):
            case $this->crm->getClientId('jamba', 'clm'):
            case $this->crm->getClientId('petco', 'clm'):
                return $this->getDefaultQbInfo($wo);
            case $this->crm->getClientId('family-dollar', 'clm'):
                return "EXPECTED DATE OF COMPLETION: {$this->getFormattedEcd($wo)}
CATEGORY: {$wo->getCategory()}
PRODUCT TYPE: {$wo->getType()}
WORK DESCRIPTION: {$wo->getDescription()}
";
            case $this->crm->getClientId('kdc', 'clm'):
                return "EXPECTED DATE OF COMPLETION: {$this->getFormattedEcd($wo)} 
WORK ORDER PRIORITY: {$wo->getCategory()}
JOB DESCRIPTION: {$wo->getDescription()}
";
        }
    }

    /**
     * Get qb_info for GFS
     *
     * @param WorkOrder $wo
     *
     * @return string
     */
    protected function getGfsQbInfo(WorkOrder $wo)
    {
        return 'EXPECTED DATE OF COMPLETION: ' . $this->getFormattedEcd($wo);
    }

    /**
     * Get default qb_info
     *
     * @param WorkOrder $wo
     *
     * @return string
     */
    protected function getDefaultQbInfo(WorkOrder $wo)
    {
        return "EXPECTED DATE OF COMPLETION: {$this->getFormattedEcd($wo)}
CATEGORY: {$wo->getCategory()}
PRODUCT TYPE:  {$wo->getType()}
WORK DESCRIPTION:{$wo->getDescription()}
";
    }

    /**
     * Get ECD in m/d/Y format
     *
     * @param WorkOrder $wo
     *
     * @return string
     */
    protected function getFormattedEcd(WorkOrder $wo)
    {
        $expectedCompletionDate = $wo->getExpectedCompletionDate();
        if ($expectedCompletionDate) {
            return Carbon::parse($expectedCompletionDate)->format('m/d/Y');
        }
        
        return null;
    }
}
