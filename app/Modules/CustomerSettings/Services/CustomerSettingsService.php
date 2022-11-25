<?php

namespace App\Modules\CustomerSettings\Services;

use App\Modules\Asset\Models\AssetLinkFileRequired;
use App\Modules\CustomerSettings\Repositories\CustomerInvoiceSettingsRepository;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsRepository;
use App\Modules\File\Models\LinkFileRequired;
use App\Modules\MsDynamics\Services\MsDynamicsService;
use App\Modules\MsDynamics\SlRecordsManager;
use App\Modules\TimeSheet\Services\TimeSheetService;
use App\Modules\Type\Models\Type;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Schema;

class CustomerSettingsService
{

    /**
     * @var string
     */
    const ALL_ASSET_SYSTEM_TYPES = 'all';

    /**
     * @var Container
     */
    protected $app;

    /**
     * @var CustomerSettingsRepository
     */
    protected $customerSettingsRepository;
    /**
     * @var CustomerInvoiceSettingsRepository
     */
    protected $customerInvoiceSettingsRepository;

    /**
     * CustomerSettingsService constructor.
     *
     * @param Container                         $app
     * @param CustomerSettingsRepository        $customerSettingsRepository
     * @param CustomerInvoiceSettingsRepository $customerInvoiceSettingsRepository
     */
    public function __construct(
        Container $app,
        CustomerSettingsRepository $customerSettingsRepository,
        CustomerInvoiceSettingsRepository $customerInvoiceSettingsRepository
    ) {
        $this->app = $app;
        $this->customerSettingsRepository = $customerSettingsRepository;
        $this->customerInvoiceSettingsRepository = $customerInvoiceSettingsRepository;
    }

    /**
     * @return mixed
     */
    public function getPhotoTypes()
    {
        return $this->getTypesByType('asset_pictures');
    }

    /**
     * @return mixed
     */
    public function getWorkOrderPhotoTypes()
    {
        return $this->getTypesByType('wo_pictures');
    }

    /**
     * @return mixed
     */
    public function getAssetTypes()
    {
        return $this->getTypesByType('asset_types');
    }

    /**
     * @return mixed
     */
    public function getWorkOrderTypes()
    {
        return $this->getTypesByType('wo_type');
    }

    /**
     * @return mixed
     */
    public function getAssetSystemTypes()
    {
        return $this->getTypesByType('asset_system_types');
    }

    /**
     * @param string $type
     *
     * @return mixed
     */
    private function getTypesByType($type)
    {
        $types = [];
        $typesList = Type::where('type', '=', $type)
            ->orderBy('type_value')
            ->pluck('type_value', 'type_id')
            ->all();

        foreach ($typesList as $typeId => $typeValue) {
            $types[] = ['value' => $typeId, 'label' => $typeValue];
        }

        return $types;
    }

    /**
     * @param int $customerSettingsID
     *
     * @throws Exception
     */
    public function saveLinkFileRequired($customerSettingsID)
    {
        $assetData = request()->all();
        $this->app['db']->transaction(function () use ($customerSettingsID, $assetData) {
            $existingLinkFileRequiredIds = [];

            foreach ($assetData['settings'] as $assetTypeId => $settingsData) {
                if ($assetTypeId === self::ALL_ASSET_SYSTEM_TYPES) {
                    $assetTypeId = null; //asset_system_type_id for all system types is NULL
                }

                foreach ($settingsData as $data) {
                    $workOrderTypeId = $data['work_order_type_id'];

                    foreach ($data['files'] as $file) {
                        $values = $this->prepareLinkFileData($file);

                        $linkFileRequired = $this->getLinkFileRequiredSettings(
                            $customerSettingsID,
                            $file['file_type_id'],
                            $assetTypeId,
                            $workOrderTypeId
                        );

                        if (!$linkFileRequired) {
                            $values['customer_settings_id'] = $customerSettingsID;
                            $values['file_type_id'] = $file['file_type_id'];
                            $values['wo_type_id'] = $workOrderTypeId;
                            $values['asset_system_type_id'] = $assetTypeId;

                            $cLinkFileRequired = $this->createFileLinkRequired($values);

                            $existingLinkFileRequiredIds[] = $cLinkFileRequired->asset_link_file_required_id;
                        } else {
                            $this->updateFileLinkRequired($linkFileRequired->asset_link_file_required_id, $values);

                            $existingLinkFileRequiredIds[] = $linkFileRequired->asset_link_file_required_id;
                        }
                    }
                }
            }

            $this->removeLinkFileRequired($customerSettingsID, $existingLinkFileRequiredIds);
        });
    }

    /**
     * @param int $customerSettingsID
     *
     * @return array|object
     * @throws Exception
     */
    public function getLinkFileRequired($customerSettingsID)
    {
        $assetData = app(AssetLinkFileRequired::class)
            ->where('customer_settings_id', '=', $customerSettingsID)
            ->get();

        $formattedData = [];
        $files = [];

        foreach ($assetData as $assetFileRequired) {
//            if ($assetFileRequired['required'] || $assetFileRequired['required_once'] || $assetFileRequired['visible']) {
            $assetTypeId = is_null($assetFileRequired->asset_system_type_id) ? self::ALL_ASSET_SYSTEM_TYPES : $assetFileRequired->asset_system_type_id;

            if (empty($assetFileRequired->location_type_id)) {
                $assetFileRequired->location_type_id = getTypeIdByKey('required_asset_file_locations.all');
            }

            $files[$assetTypeId][$assetFileRequired->wo_type_id][] = [
                'required'         => (boolean)$assetFileRequired->required,
                'required_once'    => (boolean)$assetFileRequired->required_once,
                'visible'          => (boolean)$assetFileRequired->visible,
                'scan_qr_code'     => (boolean)$assetFileRequired->scan_qr_code,
                'location_type_id' => $assetFileRequired->location_type_id,
                'file_type_id'     => $assetFileRequired->file_type_id,
                'color'            => $assetFileRequired->color
            ];
//            }
        }

        if ($files) {
            foreach ($files as $systemKey => $woTypeFiles) {
                foreach ($woTypeFiles as $workOrderTypeId => $woTypeFile) {
                    $formattedData[$systemKey][] = [
                        'work_order_type_id' => $workOrderTypeId,
                        'files'              => array_values($woTypeFile)
                    ];
                }
            }
        }

        if (!$formattedData) {
            return (object)null;
        }
        return $formattedData;
    }

    /**
     * @param int $customerSettingsId
     * @param int $fileTypeId
     * @param int $assetTypeId
     * @param int $workOrderTypeId
     *
     * @return mixed
     */
    private function getLinkFileRequiredSettings($customerSettingsId, $fileTypeId, $assetTypeId, $workOrderTypeId)
    {
        $query = app(AssetLinkFileRequired::class)
            ->where('customer_settings_id', '=', $customerSettingsId)
            ->where('wo_type_id', '=', $workOrderTypeId)
            ->where('file_type_id', '=', $fileTypeId);
        if (is_null($assetTypeId)) {
            $query->whereNull('asset_system_type_id');
        } else {
            $query->where('asset_system_type_id', '=', $assetTypeId);
        }

        return $query->first();
    }

    /**
     * @param array $values
     *
     * @return
     */
    private function createFileLinkRequired($values)
    {
        return app(AssetLinkFileRequired::class)
            ->create($values);
    }

    /**
     * @param int   $recordId
     * @param array $values
     *
     * @return
     */
    private function updateFileLinkRequired($recordId, $values)
    {
        return app(AssetLinkFileRequired::class)
            ->find($recordId)
            ->update($values);
    }

    /**
     * @param array $input
     *
     * @return array
     */
    private function prepareLinkFileData($input)
    {
        //defaults
        $required = 0;
        $requiredOnce = 0;
        $visible = 0;
        $scanQrCode = 0;
        $color = null;

        if ($input['required']) {
            $required = 1;
        }
        if ($input['required_once']) {
            $requiredOnce = 1;
        }
        if ($input['visible']) {
            $visible = 1;
        }
        if ($input['scan_qr_code']) {
            $scanQrCode = 1;
        }
        if (isset($input['color']) && $input['color']) {
            $color = $input['color'];
        }

        $locationTypeId = $input['location_type_id'];
        if (empty($locationTypeId)) {
            $locationTypeId = getTypeIdByKey('required_asset_file_locations.all');
        }
        
        return [
            'required'         => $required,
            'required_once'    => $requiredOnce,
            'visible'          => $visible,
            'scan_qr_code'     => $scanQrCode,
            'location_type_id' => $locationTypeId,
            'color'            => $color
        ];
    }

    /**
     * @param int $customerSettingsId
     */
    public function deleteLinkFileRequired($customerSettingsId)
    {
        $data = request()->all();
        $fileTypeId = null;
        $assetSystemTypeId = null;
        $workTypeId = null;

        if (isset($data['file_type_id']) && $data['file_type_id']) {
            $fileTypeId = (int)$data['file_type_id'];
        }
        if (isset($data['system_type_id']) && $data['system_type_id'] === self::ALL_ASSET_SYSTEM_TYPES) {
            $assetSystemTypeId = null; //asset_system_type_id for all system types is NULL
        } elseif (isset($data['system_type_id']) && $data['system_type_id']) {
            $assetSystemTypeId = (int)$data['system_type_id'];
        }
        if (isset($data['work_order_type_id']) && $data['work_order_type_id']) {
            $workTypeId = (int)$data['work_order_type_id'];
        }

        $this->app['db']->transaction(function () use (
            $customerSettingsId,
            $fileTypeId,
            $assetSystemTypeId,
            $workTypeId
        ) {
            $query = app(AssetLinkFileRequired::class)
                ->where('customer_settings_id', '=', $customerSettingsId);

            if ($fileTypeId && $workTypeId) {
                $query->where('file_type_id', '=', $fileTypeId)
                    ->where('wo_type_id', '=', $workTypeId);
            } elseif ($workTypeId) {
                $query->where('wo_type_id', '=', $workTypeId);
            }

            if (is_null($assetSystemTypeId)) {
                $query->whereNull('asset_system_type_id');
            } else {
                $query->where('asset_system_type_id', '=', $assetSystemTypeId);
            }

            $query->update(['required' => 0, 'required_once' => 0, 'visible' => 0]);
        });
    }

    /**
     * @param int $customerSettingsId
     */
    public function saveWorkOrderFileLinkRequired($customerSettingsId)
    {
        $workOrderRequiredFiles = request()->all();
        $this->app['db']->transaction(function () use ($customerSettingsId, $workOrderRequiredFiles) {
            $existingLinkFileRequiredIds = [];

            foreach ($workOrderRequiredFiles['settings'] as $workOrderRequiredFile) {
                $workOrderRequiredFileSettings = $this->getWorkOrderFileRequiredSettings(
                    $customerSettingsId,
                    $workOrderRequiredFile['file_type_id']
                );

                $values = [
                    'required'      => (int)$workOrderRequiredFile['required'],
                    'required_once' => (int)$workOrderRequiredFile['required_once'],
                ];

                if ($workOrderRequiredFileSettings) {
                    $this->updateWorkOrderFileSettings($values, $workOrderRequiredFileSettings->link_file_required_id);

                    $existingLinkFileRequiredIds[] = $workOrderRequiredFileSettings->link_file_required_id;
                } else {
                    $values['file_type_id'] = $workOrderRequiredFile['file_type_id'];
                    $values['color'] = $workOrderRequiredFile['color'];
                    $values['type'] = 'work_order';
                    $values['file_type'] = 'picture';
                    $values['customer_settings_id'] = $customerSettingsId;

                    $cLinkFileRequired = $this->insertWorkOrderFileSettings($values);

                    $existingLinkFileRequiredIds[] = $cLinkFileRequired->link_file_required_id;
                }
            }

            $this->removeWorkOrderFileRequired($customerSettingsId, $existingLinkFileRequiredIds);
        });
    }

    /**
     * @param int $customerSettingsId
     * @param int $fileTypeId
     *
     * @return mixed
     */
    private function getWorkOrderFileRequiredSettings($customerSettingsId, $fileTypeId)
    {
        return app(LinkFileRequired::class)
            ->where('customer_settings_id', '=', $customerSettingsId)
            ->where('type', '=', 'work_order')
            ->where('file_type', '=', 'picture')
            ->where('file_type_id', '=', $fileTypeId)
            ->first();
    }

    /**
     * @param array $values
     * @param int   $recordId
     *
     * @return
     */
    private function updateWorkOrderFileSettings($values, $recordId)
    {
        return app(LinkFileRequired::class)
            ->find($recordId)
            ->update($values);
    }

    /**
     * @param array $values
     *
     * @return
     */
    private function insertWorkOrderFileSettings($values)
    {
        return app(LinkFileRequired::class)
            ->create($values);
    }

    /**
     * @param int $customerSettingsId
     *
     * @return mixed
     */
    public function getWorkOrderFileLinkRequired($customerSettingsId)
    {
        return app(LinkFileRequired::class)
            ->where('customer_settings_id', '=', $customerSettingsId)
            ->where('type', '=', 'work_order')
            ->where('file_type', '=', 'picture')
            ->get();
    }

    /**
     * @param int $customerSettingsId
     */
    public function deleteWorkOrderFileLinkRequired($customerSettingsId)
    {
        $input = request()->all();
        $fileTypeId = $input['file_type_id'];
        $this->app['db']->transaction(function () use ($customerSettingsId, $fileTypeId) {
            app(LinkFileRequired::class)
                ->where('customer_settings_id', '=', $customerSettingsId)
                ->where('type', '=', 'work_order')
                ->where('file_type', '=', 'picture')
                ->where('file_type_id', '=', $fileTypeId)
                ->update(['required' => 0, 'required_once' => 0]);
        });
    }

    /**
     * @param int $customerSettingsId
     *
     * @param     $existingLinkFileRequiredIds
     *
     * @return void
     */
    private function removeLinkFileRequired($customerSettingsId, $existingLinkFileRequiredIds)
    {
        $allLinkFileRequiredIds = app(AssetLinkFileRequired::class)
            ->where('customer_settings_id', '=', $customerSettingsId)
            ->pluck('asset_link_file_required_id')
            ->all();

        $toRemove = array_diff($allLinkFileRequiredIds, $existingLinkFileRequiredIds);
        if ($toRemove) {
            app(AssetLinkFileRequired::class)
                ->where('customer_settings_id', '=', $customerSettingsId)
                ->whereIn('asset_link_file_required_id', $toRemove)
                ->get()
                ->each(function ($row) {
                    $row->delete();
                });
        }
    }

    /**
     * @param int $customerSettingsId
     * @param     $existingLinkFileRequiredIds
     */
    private function removeWorkOrderFileRequired($customerSettingsId, $existingLinkFileRequiredIds)
    {
        $allLinkFileRequiredIds = app(LinkFileRequired::class)
            ->where('customer_settings_id', '=', $customerSettingsId)
            ->where('type', '=', 'work_order')
            ->where('file_type', '=', 'picture')
            ->pluck('link_file_required_id')
            ->all();

        $toRemove = array_diff($allLinkFileRequiredIds, $existingLinkFileRequiredIds);
        if ($toRemove) {
            app(LinkFileRequired::class)
                ->where('customer_settings_id', '=', $customerSettingsId)
                ->whereIn('link_file_required_id', $toRemove)
                ->get()
                ->each(function ($row) {
                    $row->delete();
                });
        }
    }

    /**
     * Get customer settings
     *
     * @param int  $id
     * @param bool $full
     *
     * @return array
     */
    public function show($id, $full = false)
    {
        $settings = $this->customerSettingsRepository->find($id);
        $metaData = json_decode($settings->meta_data, true);
        $basicSettings = $this->parseBasicSettings($settings);
        $metaDataSettings = $this->parseMetaData($settings, $metaData);
        return [
            'items' => array_merge($basicSettings, $metaDataSettings)
        ];
    }

    /**
     * Update customer settings
     *
     * @param       $id
     * @param array $settingsToUpdate
     *
     * @return bool
     */
    public function updateSettings($id, array $settingsToUpdate)
    {
        $settings = $this->customerSettingsRepository->find($id);
        $columns = Schema::getColumnListing('customer_settings');
        if (!$metaData = json_decode($settings->meta_data, true)) {
            $metaData = [];
        };

        $metadataHash = md5($settings->meta_data);
        foreach ($settingsToUpdate as $key => $values) {
            if ($values['value'] === false) {
                $values['value'] = 0;
            }

            if ($values['value'] === true) {
                $values['value'] = 1;
            }

            //exception for the footer section
            if ($key === 'footer' && isset($values['value'])) {
                $settings->footer_file_id = null;
                $settings->footer_text = null;
                if ($values['value'] !== 'none' && !empty($values['options'])) {
                    foreach ($values['options'] as $option) {
                        if ($option['value'] === $values['value']) {
                            foreach ($option['additional'] as $additional) {
                                if (in_array($additional['name'], $columns)) {
                                    $settings->{$additional['name']} = $additional['value'];
                                }
                            }
                        }
                    }
                }
            } else {
                //update columns in customer_settings table
                if (in_array($key, $columns)) {
                    $settings->$key = $values['value'];
                } else {
                    //update fields in meta_data object
                    if (isset($metaData[$key])) {
                        if (isset($metaData[$key]['answer']) && !is_array($metaData[$key]['answer'])) {
                            $metaData[$key]['answer'] = $values['value'];
                        }
                    } else {
                        if (!is_null($values['value'])) {
                            $metaData[$key] = [
                                'answer' => $values['value']
                            ];
                        }
                    }

                    if (config('app.crm_user') == 'bfc') {
                        if ($key === 'Communication_system?') {
                            //$this->updateCustomerInvoiceSettings($settings->company_person_id, $values['value']);

                            $this->customerSettingsRepository->updateCommunicationSystemFromBfcInv(
                                $settings->company_person_id,
                                $values['value']
                            );
                        }

                        if ($key === 'PleatLink_approved?') {
                            $this->updatePleatLinkApproved($settings->company_person_id, $values['value']);
                        }
                    }
                }
            }
        }
        //if there were any changes in the meta data, overwrite it
        if (md5(json_encode($metaData)) !== $metadataHash) {
            $settings->meta_data = json_encode($metaData);
        }

        return (bool)$settings->save();
    }

    /**
     * Build array based on fields from customer_settings table
     *
     * @param $settings
     *
     * @return array
     */
    private function parseBasicSettings($settings)
    {
        $fields = $this->getBasicSettingsFields();
        $options = [];
        foreach ($fields as $name => $field) {
            $options[$name] = [
                'label'            => $field['label'],
                'name'             => $name,
                'description'      => null,
                'type'             => $field['type'],
                'value'            => $this->castToBoolForCheckbox($field['type'], $settings->$name),
                'additional'       => [],
                'available_in_app' => 0,
                'is_active'        => 1,
            ];
        }
        $selected = 'none';
        if (!empty($settings->footer_file_id)) {
            $selected = 'file';
        } else {
            if (!empty($settings->footer_text)) {
                $selected = 'text';
            }
        }
        $options['footer'] = [
            'label'            => 'Work Order PDF Footer text',
            'name'             => 'footer',
            'description'      => 'Attention! Any footer text source changes removes previously stored data.',
            'type'             => 'radio',
            'value'            => $selected,
            'options'          => [
                [
                    'label'       => 'none',
                    'description' => null,
                    'value'       => 'none',
                    'additional'  => []
                ],
                [
                    'label'       => 'Attach text from PDF',
                    'description' => null,
                    'value'       => 'file',
                    'additional'  => [
                        [
                            'label'       => 'Attach text from file',
                            'name'        => 'footer_file_id',
                            'description' => null,
                            'value'       => $settings->footer_file_id,
                            'type'        => 'file',
                            'accept'      => 'pdf'
                        ]
                    ]
                ],
                [
                    'label'       => 'Enter own text',
                    'description' => null,
                    'value'       => 'text',
                    'additional'  => [
                        [
                            'label'       => 'Attach text from file',
                            'name'        => 'footer_text',
                            'description' => null,
                            'value'       => $settings->footer_text,
                            'type'        => 'textarea'
                        ]
                    ]
                ]
            ],
            'additional'       => [],
            'available_in_app' => 0,
            'is_active'        => 1,
        ];
        return $options;
    }

    /**
     * Build array based on fields from meta_data field from customer_settings table
     *v
     *
     * @param $settings
     * @param $metaData
     *
     * @return array
     */
    private function parseMetaData($settings, $metaData)
    {
        $fields = $this->getMetadataFields();
        $options = [];
        foreach ($fields as $field) {
            $name = isset($field['name'])
                ? $field['name']
                : str_replace(' ', '_', $field['label']);
            $options[$name] = $this->buildFormObjectForMetaData($name, $field, $settings, $metaData);
        }
        return $options;
    }

    private function buildFormObjectForMetaData($name, $field, $settings, $metaData = [])
    {
        $options = [];
        if (isset($field['options'])) {
            foreach ($field['options'] as $option) {
                if (is_array($option)) {
                    $label = $option['label'];
                    $value = $option['value'];
                } else {
                    $label = $value = $option;
                }

                if ($name === 'Communication_system?' && $option === 'Lob') {
                    $label = 'mail.lob.com';
                }

                $options[] = [
                    'label'       => $label,
                    'description' => null,
                    'value'       => $value,
                    'additional'  => []
                ];
            }
        }

        if (config('app.crm_user') == 'bfc') {
            //For Communication system overwrite value from customer_invoice_settings
            if ($name === 'Communication_system?') {
                //$communicationSystem = $this->customerInvoiceSettingsRepository->getCommunicationSystemValue(
                //    $settings->company_person_id
                //);

                $communicationSystem = $this->customerSettingsRepository->getCommunicationSystemFromBfcInv(
                    $settings->company_person_id
                );

                if ($communicationSystem) {
                    $metaData[$name]['answer'] = $communicationSystem;
                }
            }
        }

        $value = isset($metaData[$name]['answer']) ? $metaData[$name]['answer'] : null;

        return [
            'label'            => $field['label'],
            'name'             => $name,
            'description'      => !empty($field['description']) ? $field['description'] : null,
            'type'             => $field['type'],
            'value'            => $this->castToBoolForCheckbox($field['type'], $value),
            'options'          => $options,
            'additional'       => [],
            'available_in_app' => 0,
            'is_active'        => 1,
        ];
    }

    /**
     * Get customer settings id by table name and table id
     * @param  string  $tableName
     * @param  int  $tableId
     *
     * @return null
     */
    public function getCustomerSettingsIdByTableNameAndTableId($tableName, $tableId)
    {
        switch ($tableName) {
            case 'time_sheet':
                return app(TimeSheetService::class)->getCustomerSettingsIdByTimeSheetId($tableId);
        }

        return null;
    }
    
    /**
     * @param      $companyPersonId
     * @param      $value
     * @param null $customerInvoiceSettingsOptions
     *
     * @return boolean|null
     */
    private function updateCustomerInvoiceSettings($companyPersonId, $value, $customerInvoiceSettingsOptions = null)
    {
        //Customer invoice settings:
        if ($companyPersonId) {
            $customerInvoiceSettings = $this->customerInvoiceSettingsRepository->findByCompanyPersonId($companyPersonId);
            $customerInvoiceSettings->active = 1;
            if ($value === 'Lob') {
                $customerInvoiceSettings->delivery_method = 'mail';
            } elseif ($value === 'Email') {
                $customerInvoiceSettings->delivery_method = 'email';
            } else {
                //if none send invoice set disable customer settings
                $customerInvoiceSettings->delivery_method = 'email';
                $customerInvoiceSettings->active = 0;
            }
            if ($customerInvoiceSettingsOptions) {
                $customerInvoiceSettings->options = json_encode($customerInvoiceSettingsOptions);
            }
            return $customerInvoiceSettings->save();
        }
        return null;
    }

    public function getBasicSettingsFields()
    {
        return [
//            'required_completion_code'      => [
//                'type'  => 'checkbox',
//                'label' => 'Is Work Order completion code required?',
//            ],
            'site_issue_required'           => [
                'type'  => 'checkbox',
                'label' => 'Is Site Issues required?',
            ],
            'required_work_order_signature' => [
                'type'  => 'checkbox',
                'label' => 'Is Manager\'s signature required after completion of work?',
            ],
            'filter_change_confirmation'    => [
                'type'  => 'checkbox',
                'label' => 'Show Skip Asset Service switch?',
            ],
            'ivr_number'                    => [
                'type'  => 'text',
                'label' => 'IVR Number',
            ],
            'ivr_pin'                       => [
                'type'  => 'text',
                'label' => 'IVR Pin',
            ],
            'ivr_from_store'                => [
                'type'  => 'checkbox',
                'label' => 'IVR from store?',
            ],
            'ivr_number_forward'            => [
                'type'        => 'text',
                'label'       => 'IVR Number Forward',
                'description' => 'if CRM IVR is used, it will forward the call to this number'
            ]
        ];
    }

    private function getMetadataFields()
    {
        $communicationSystemOptions = [
            'none',
            'One by SMS Assist',
            'Ariba',
            'Big Sky',
            'Brinco',
            'Dollar General',
            'EcoTrak',
            'Facility Maintenance',
            'FM Pilot',
            'FM Pilot2',
            'Maintenance Connection',
            'Market Place Support',
            'Maximo',
            'myFSN',
            'NEST',
            'Roth',
            'Service Channel',
            'Total Facility',
            'Verisae',
            'Work Oasis',
            ['value' => 'Work Order Network', 'label' => 'CorrigoPro'],
            'Coupa',
            ['value' => 'Lob', 'label' => 'mail.lob.com'],
            'Email',
            
            'Comfort Systems',
            'True Source',
            '23rd Group',
            'Officetrax',
            'Service Now',
            'Maintenance ETC',
            'Mercury',
            'Nuvolo',
            'Trinity',
            'RSM Maintenance',
            'Fitness EMS'
        ];

        foreach ($communicationSystemOptions as $key => $value) {
            if (!is_array($value)) {
                $communicationSystemOptions[$key] = [
                    'value' => $value,
                    'label' => $value,
                ];
            }
        }

        usort($communicationSystemOptions, function ($item1, $item2) {
            return strtolower($item1['label']) <=> strtolower($item2['label']);
        });
        
        return [
            [
                'type'  => 'text',
                'label' => 'Markup rate?',
            ],
            [
                'type'  => 'text',
                'label' => 'Lighting markup?',
            ],
            [
                'type'    => 'radio',
                'label'   => 'Show markup on invoice?',
                'options' => [
                    'no',
                    'yes'
                ]
            ],
            [
                'type'    => 'select',
                'label'   => 'Customer Work order System',
                'name'    => 'Communication_system?',
                'options' => $communicationSystemOptions
            ],
            [
                'type'  => 'text',
                'label' => 'Communication system username?',
            ],
            [
                'type'  => 'text',
                'label' => 'Communication system password?',
            ],
            [
                'type'  => 'text',
                'label' => 'Communication system client name?',
            ],
            [
                'type'    => 'radio',
                'label'   => 'Upload invoice document to communication system?',
                'options' => [
                    'no',
                    'yes',
                ]
            ],
            [
                'type'  => 'textarea',
                'label' => 'Communication system settings?',
            ],
            [
                'type'    => 'radio',
                'label'   => 'Use incurred section for quotes?',
                'options' => [
                    'General Incurred',
                    'Detailed Incurred',
                    'No Incurred'
                ]
            ],
            [
                'type'  => 'text',
                'label' => 'Information to show when over NTE?',
            ],
            [
                'type'  => 'text',
                'label' => 'Information to show when under NTE?',
            ],
            [
                'type'    => 'radio',
                'label'   => 'Use defined Travel Rates?',
                'options' => [
                    'no',
                    'yes',
                ]
            ],
            [
                'type'    => 'radio',
                'label'   => 'Payment terms',
                'options' => [
                    'Payable in Advance',
                    'Due on Receipt',
                    'Net in 15 Days',
                    'Net in 30 Days',
                    'Net in 45 Days',
                    'Net in 60 Days',
                    'Net in 75 Days',
                    '2% 10 Net 30'
                ]
            ],
            [
                'type'  => 'textarea',
                'label' => 'Invoice footer',
            ],
            [
                'type'    => 'radio',
                'label'   => 'Show PO Number on invoice',
                'options' => [
                    'No',
                    'Yes'
                ]
            ],
            [
                'type'    => 'radio',
                'label'   => 'Tax Exempt',
                'options' => [
                    'No',
                    'Yes'
                ]
            ],
            [
                'type'    => 'radio',
                'label'   => 'Tax Exempt States',
                'options' => [
                    'All',
                    'Only In',
                    'Not In'
                ]
            ],
            [
                'type'        => 'textarea',
                'label'       => 'Tax Exempt States List',
                'description' => 'States(separated with \',\')'
            ],
            [
                'type'    => 'radio',
                'label'   => 'Disable requiring photos?',
                'options' => [
                    'No',
                    'Yes'
                ]
            ],
            [
                'type'    => 'radio',
                'label'   => 'PleatLink approved?',
                'options' => [
                    'No',
                    'Yes'
                ]
            ],
            [
                'type'    => 'radio',
                'label'   => 'PleatLink capacity?',
                'options' => [
                    'Standard',
                    'Hi capacity'
                ]
            ],
            [
                'type'    => 'radio',
                'label'   => 'Not site issues?',
                'options' => [
                    'No',
                    'Yes'
                ]
            ],
            [
                'type'    => 'radio',
                'label'   => 'Prioritized Assets - Customer Paying?',
                'options' => [
                    'No',
                    'Yes'
                ]
            ],
            [
                'type'  => 'text',
                'label' => 'External App URL',
            ],
        ];
    }

    private function castToBoolForCheckbox($type, $value)
    {
        if ($type === 'checkbox') {
            return (bool)$value;
        }

        return $value;
    }

    private function updatePleatLinkApproved($company_person_id, $value)
    {
        $customer = app(SlRecordsManager::class)->findSlRecordId('person', $company_person_id);
        if ($customer) {
            app(MsDynamicsService::class)->updatePleatLinkApproved($customer, $value === 'Yes');
        }
    }
}
