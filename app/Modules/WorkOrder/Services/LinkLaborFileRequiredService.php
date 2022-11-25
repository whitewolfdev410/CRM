<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\WorkOrder\Repositories\LinkLaborFileRequiredRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

class LinkLaborFileRequiredService
{
    /**
     * @var Container
     */
    protected $app;
    /**
     * @var LinkLaborFileRequiredRepository
     */
    protected $linkLaborFileRequiredRepository;

    /**
     * Initialize class
     *
     * @param Container                       $app
     * @param LinkLaborFileRequiredRepository $linkLaborFileRequiredRepository
     */
    public function __construct(Container $app, LinkLaborFileRequiredRepository $linkLaborFileRequiredRepository)
    {
        $this->app = $app;
        $this->linkLaborFileRequiredRepository = $linkLaborFileRequiredRepository;
    }

    /**
     * Get labor link files
     *
     * @param int $customerSettingsId
     *
     * @return mixed
     */
    public function getLaborLinkFiles(int $customerSettingsId)
    {
        return $this->linkLaborFileRequiredRepository->getLaborLinkFilesByCustomerSettingsId($customerSettingsId);
    }

    /**
     * @param int     $customerSettingsId
     * @param Request $request
     */
    public function saveLaborLinkFiles(int $customerSettingsId, Request $request)
    {
        $linkLaborFiles = $request->all();

        $this->app['db']->transaction(function () use ($customerSettingsId, $linkLaborFiles) {
            $existingLinkFileRequiredIds = [];

            foreach ($linkLaborFiles['settings'] as $linkLaborFileRequired) {
                $linkLaborFileRequiredSettings = $this->linkLaborFileRequiredRepository
                    ->getLaborLinkFileByCustomerSettingsIdAndInventoryId(
                        $customerSettingsId,
                        $linkLaborFileRequired['inventory_id']
                    );

                $values = [
                    'required' => (int)$linkLaborFileRequired['required'],
                    'view_only' => (int)$linkLaborFileRequired['view_only']
                ];

                if ($linkLaborFileRequiredSettings) {
                    $this->linkLaborFileRequiredRepository->updateWithIdAndInput(
                        $linkLaborFileRequiredSettings->link_labor_file_required_id,
                        $values
                    );

                    $existingLinkFileRequiredIds[] = $linkLaborFileRequiredSettings->link_labor_file_required_id;
                } else {
                    $values['inventory_id'] = $linkLaborFileRequired['inventory_id'];
                    $values['color'] = $linkLaborFileRequired['color'];
                    $values['customer_settings_id'] = $customerSettingsId;

                    $cLinkLaborFileRequired = $this->linkLaborFileRequiredRepository->create($values);

                    $existingLinkFileRequiredIds[] = $cLinkLaborFileRequired->link_labor_file_required_id;
                }
            }

            $this->linkLaborFileRequiredRepository
                ->removeNotExistingFiles($customerSettingsId, $existingLinkFileRequiredIds);
        });
    }
}
