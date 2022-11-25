<?php

namespace App\Modules\CustomerSettings\Services;

use App\Modules\Type\Models\Type;
use Exception;
use Illuminate\Contracts\Container\Container;
use \PhpOffice\PhpSpreadsheet\IOFactory;

class CustomerSettingsPictureRequirements
{
    private $app;
    private $db;
    private $command;
    private $path;

    private $types;

    private $cellsData;
    private $fileTypes;

    /**
     * Constructor
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->db = $app['db'];
    }

    /**
     * Set xlsx path
     * @param $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * Output info
     * @param  string $string
     * @return void
     */
    private function outputInfo($string)
    {
        if ($this->command) {
            $this->command->info($string);
        }
    }

    /**
     * Output error
     * @param  string $string
     * @return void
     */
    private function outputError($string)
    {
        if ($this->command) {
            $this->command->error($string);
        }
    }

    /**
     * Set output command instance
     * @param mixed $command
     */
    public function setOutput($command)
    {
        $this->command = $command;
    }

    /**
     * @throws Exception
     */
    public function import()
    {
        try {
            $excel = $this->loadSheets();
            $this->processSheets($excel);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Load sheet with results
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    protected function loadSheets()
    {
        $inputFileType = IOFactory::identify($this->path);
        $objReader = IOFactory::createReader($inputFileType);
        $objPHPExcel = $objReader->load($this->path);

        return $objPHPExcel;
    }

    /**
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $objPHPExcel
     * @throws Exception
     */
    protected function processSheets($objPHPExcel)
    {
        $sheetNames = $objPHPExcel->getSheetNames();
        foreach ($sheetNames as $sheetName) {
            [$filterType, $coilType] = $this->getTypeBySheetName($sheetName);

            //skip not supported sheet name
            if (!$filterType && !$coilType) {
                continue;
            }

            $this->cellsData = $objPHPExcel->getSheetByName($sheetName)->toArray();
            $this->fileTypes = array_shift($this->cellsData);
            $this->mapTypes($this->fileTypes);

            $companyFileLinkSettings = $this->processCell();
            $this->updateFileLinkSettings($companyFileLinkSettings, $filterType, $coilType);
        }
    }

    /**
     * @param array $companyFileLinkSettings
     * @param bool $filterType
     * @param bool $coilType
     */
    protected function updateFileLinkSettings($companyFileLinkSettings, $filterType, $coilType)
    {
        foreach ($companyFileLinkSettings as $customerSettingsId => $companyFileLinkSetting) {
            //$this->deleteLinkFileRequired($customerSettingsId); //@todo: make sure if we should remove old settings
            foreach ($companyFileLinkSetting as $fileTypeId) {
                if (!$this->fileTypeExists($customerSettingsId, $fileTypeId)) {
                    $this->createLinkFileRequired($customerSettingsId, $fileTypeId, $filterType, $coilType);
                }
            }

            $this->outputInfo('Updated settings for customer settings ID ' . $customerSettingsId);
        }
    }


    /**
     * @return array
     */
    protected function processCell()
    {
        $companyFileLinkSettings = [];
        $customerSettingsId = 0;

        foreach ($this->cellsData as $rowData) {
            foreach ($rowData as $rowKey => $companyData) {
                if ($rowKey == 0) {
                    $slCompanyId = $companyData;
                    $customerSettingsId = $this->getCustomerSettingsIdByCompanySlId($slCompanyId);
                }

                if ($customerSettingsId) {
                    $isTypeSelected = $companyData === 'Y' ? true : false;
                    if ($isTypeSelected) {
                        $selectedType = $this->fileTypes[$rowKey];
                    } else {
                        $selectedType = null;
                    }

                    if ($selectedType) {
                        $selectedTypeId = array_search($selectedType, $this->types);
                        $companyFileLinkSettings[$customerSettingsId][] = $selectedTypeId;
                    }
                } else {
                    if ($rowKey == 0) {
                        $this->outputError('Missing customer settings for '. $slCompanyId);
                    }
                }
            }
        }

        return $companyFileLinkSettings;
    }

    /**
     * @param string $sheetName
     * @return array
     */
    protected function getTypeBySheetName($sheetName)
    {
        switch ($sheetName) {
            case 'Filter':
                $filterType = 1;
                $coilType = 0;
                break;
            case 'Coil':
                $filterType = 0;
                $coilType = 1;
                break;
            default:
                $filterType = 0;
                $coilType = 0;
        }

        return [$filterType, $coilType];
    }

    /**
     * @param array $fileTypes
     * @throws Exception
     */
    protected function mapTypes($fileTypes)
    {
        foreach ($fileTypes as $key => $fileType) {
            if ($key == 0) {
                continue;
            }
            $type = Type::where('type', 'asset_pictures')
                ->where('type_value', $fileType)
                ->first();

            if (!$type) {
                throw new Exception('Missing asset_pictures type');
            }
            $this->types[$type->type_id] = $type->type_value;
        }
    }

    /**
     * @param int $customerSettingsID
     * @param int $fileTypeId
     * @param int $filter
     * @param int $coil
     * @return bool
     */
    protected function createLinkFileRequired(
        $customerSettingsID,
        $fileTypeId,
        $filter = 0,
        $coil = 0
    ) {
        if (!$fileTypeId) {
            return false;
        }

        $values = [
            'customer_settings_id' => $customerSettingsID,
            'file_type_id' => $fileTypeId,
            'type' => 'asset',
            'file_type' => 'picture',
            'required' => $filter ? 1 : 0,
            'required_once' => $filter ? 1 : 0,
            'visible' => $filter ? 1 : 0,
            'coil_required' => $coil ? 1 : 0,
            'coil_required_once' => $coil ? 1 : 0,
            'coil_visible' => $coil ? 1 : 0
        ];

        //@todo: make sure if settings are set up properly
//        return $this->app['db']
//            ->table('link_file_required')
//            ->insertGetId($values);
    }

    /**
     * @param int $customerSettingsId
     * @param int $fileTypeId
     * @return bool
     */
    protected function fileTypeExists($customerSettingsId, $fileTypeId)
    {
        return $this->db->table('link_file_required')
            ->where('customer_settings_id', '=', $customerSettingsId)
            ->where('file_type_id', '=', $fileTypeId)
            ->exists();
    }

    /**
     * @param int $customerSettingsID
     */
    private function deleteLinkFileRequired($customerSettingsID)
    {
        $this->db->table('link_file_required')
            ->where('customer_settings_id', '=', $customerSettingsID)
            ->where('type', '=', 'asset')
            ->delete();
    }

    /**
     * @param int $slCompanyId
     * @return int|null
     */
    protected function getCustomerSettingsIdByCompanySlId($slCompanyId)
    {
        $customerSettings = $this->db->table('customer_settings')
            ->leftJoin('sl_records', 'sl_records.record_id', '=', 'company_person_id')
            ->where('sl_records.sl_record_id', '=', $slCompanyId)
            ->where('sl_records.sl_table_name', '=', 'Customer')
            ->where('sl_records.table_name', '=', 'person')
            ->first();

        if ($customerSettings) {
            return $customerSettings->customer_settings_id;
        }

        return null;
    }
}
