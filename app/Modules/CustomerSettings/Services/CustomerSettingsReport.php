<?php

namespace App\Modules\CustomerSettings\Services;

use App\Modules\CustomerSettings\Models\CustomerSettings;
use App\Modules\Type\Models\Type;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Mail\Mailer;

class CustomerSettingsReport
{
    private $app;
    private $db;
    private $output;

    /**
     * Constructor
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->db = $app['db'];
    }

    /**
     * Set output command
     *
     * @param Command $command
     */
    public function setOutput($command)
    {
        $this->output = $command;
    }

    /**
     * Output line
     *
     * @param  string $string
     *
     * @return void
     */
    private function outputLine($string)
    {
        if ($this->output) {
            $this->output->line($string);
        }
    }

    /**
     * Output error
     *
     * @param  string $string
     *
     * @return void
     */
    private function outputError($string)
    {
        if ($this->output) {
            $this->output->error($string);
        }
    }

    /**
     * Output info
     *
     * @param  string $string
     *
     * @return void
     */
    private function outputInfo($string)
    {
        if ($this->output) {
            $this->output->info($string);
        }
    }

    /**
     * Generate report
     *
     * @param bool $sendMail
     *
     * @return string $report
     */
    public function generateReport($sendMail = false)
    {
        $this->outputLine('Generating report...');

        $customerSettings = $this->getCustomerSettings();

        $data = [];
        $map = $this->getMap();
        $types = $this->getTypes();

        $idsTemplate = [];
        foreach ($customerSettings as $customer) {
            $idsTemplate[$customer->customer_settings_id] = '';
        }

        foreach ($customerSettings as $customer) {
            $id = $customer->customer_settings_id;

            $data['Customer'][$id] = $customer->custom_1;
            $data['Required site issues?'][$id] = $customer->site_issue_required ? 'X' : '';
            $data['Required completion code?'][$id] = $customer->required_completion_code ? 'X' : '';
            $data['Completion code format'][$id] = $customer->completion_code_format;
            $data['Required WO signature'][$id] = $customer->required_work_order_signature ? 'X' : '';
            $data['Uses Authorization code'][$id] = $customer->uses_authorization_code ? 'X' : '';
            $data['Auto generate WO#'][$id] = $customer->auto_generate_work_order_number ? 'X' : '';
            $data['IVR Number'][$id] = $customer->ivr_number;
            $data['WO PDF Footer text'][$id] = $customer->footer_text;

            $metaData = json_decode($customer->meta_data, true);
            if ($metaData) {
                $required = [];

                if (isset($metaData['required_asset_files_type_id'])) {
                    $required['asset'] = $metaData['required_asset_files_type_id'];
                }

                if (isset($metaData['required_work_order_files_type_id'])) {
                    $required['workorder'] = $metaData['required_work_order_files_type_id'];
                }
                
                foreach ($metaData as $key => $setting) {
                    if (in_array(
                        $key,
                        ['action', 'module', 'update-meta-data', '%1', 'customer_settings_id', 'new_window_mode']
                    )) {
                        continue;
                    }

                    if (isset($map[$key])) {
                        $name = $map[$key];
                    } else {
                        $name = str_replace('_', ' ', $key);
                    }

                    if (!in_array($key, [
                        'required_asset_files_type_id',
                        'required_work_order_files_type_id',
                        'required_asset_files_link_id',
                        'required_work_order_files_link_id'
                    ])) {
                        if (!isset($data[$name])) {
                            $data[$name] = $idsTemplate;
                        }

                        $data[$name][$id] = $this->parseData($key, $setting, $required, $types);
                    }
                }
            }
        }

        $csv = [];
        foreach ($data as $name => $values) {
            $csv[] = $this->createCsvLine(array_merge([$name], $values));
        }

        $report = implode("", $csv);

        if ($sendMail) {
            $this->sendEmailWithReport($report);
        }

        $this->outputLine('Done.');

        return $report;
    }

    /**
     * Send mail with report
     *
     * @param $report
     */
    private function sendEmailWithReport($report)
    {
        $this->outputLine('Sending email...');

        $recipients = 'kamil.ciszewski@skrib.pl';
        $bcc = [
        ];

        $subject = 'Customer Settings report.';
        $view = 'emails.notifications.customer_settings_report';

        $data = [

        ];

        $mailer = $this->app[Mailer::class];
        $mailer->send($view, $data, function ($message) use ($recipients, $bcc, $subject, $report) {
            $message
                ->to($recipients)
                ->bcc($bcc)
                ->subject($subject)
                ->attachData($report, 'customer_settings.csv', [
                    'mime' => 'application/vnd.ms-excel',
                ]);
        });
    }

    /**
     * @return mixed
     */
    private function getCustomerSettings()
    {
        $customerSettings = $this->app[CustomerSettings::class];
        $columns = [
            'customer_settings.customer_settings_id',
            'person.custom_1',
            'customer_settings.site_issue_required',
            'customer_settings.required_completion_code',
            'customer_settings.completion_code_format',
            'customer_settings.required_work_order_signature',
            'customer_settings.uses_authorization_code',
            'customer_settings.auto_generate_work_order_number',
            'customer_settings.ivr_number',
            'customer_settings.footer_text',
            'customer_settings.meta_data'
        ];

        return $customerSettings
            ->select($columns)
            ->join('person', 'customer_settings.company_person_id', '=', 'person.person_id')
            ->orderBy('person.custom_1')
            ->where('customer_settings_id', '!=', 1)
            ->get();
    }

    /**
     * @return mixed
     */
    private function getTypes()
    {
        $types = $this->app[Type::class];
        $columns = [
            'type.type_id',
            'type.type_value'
        ];

        return $types
            ->select($columns)
            ->pluck('type_value', 'type_id')
            ->all();
    }

    /**
     * @param $key
     * @param $settings
     *
     * @return string
     */
    private function parseData($key, $settings, $required, $types)
    {
        switch ($key) {
            case 'Certificate_of_Insurance_Requirements':
            case 'How_are_invoices_submitted_to_the_customer?':
            case 'Send_W-9_&_COI_via':
            case 'States_of_operation':
                return $this->parseAnswerWithMain($settings);
                break;

            case 'required_asset_files_link_id':
                return $this->parseRequiredFilesLinkId($settings, $types);
                break;

            case 'required_asset_files':
            case 'required_asset_files_visible':
            case 'required_asset_files_required_once':
            case 'required_asset_files_coil':
            case 'required_asset_files_coil_visible':
            case 'required_asset_files_coil_required_once':
                return $this->parseRequiredFiles($settings, $types, $required['asset']);
                break;

            case 'required_work_order_files_link_id':
                return $this->parseRequiredFilesLinkId($settings, $types);
                break;

            case 'required_work_order_files':
                return $this->parseRequiredFiles($settings, $types, $required['workorder']);
                break;

            default:
                return $this->parseAnswer($settings);
        }
    }

    /**
     * @param $settings
     *
     * @return string
     */
    private function parseAnswer($settings)
    {
        if (isset($settings['answer'])) {
            $data = is_array($settings['answer']) ? $settings['answer'] : [$settings['answer']];

            if (isset($settings['additional'])) {
                foreach ($settings['additional'] as $aKey => $aValue) {
                    $data[] = $aKey . ': ' . $aValue;
                }
            }

            return implode("\r", $data);
        }

        return '';
    }

    /**
     * @param $settings
     *
     * @return string
     */
    private function parseAnswerWithMain($settings)
    {
        $data = [];

        if (isset($settings['answer']['additional'])) {
            foreach ($settings['answer']['additional'] as $aKey => $aValue) {
                if (isset($settings['answer']['main']) && in_array($aKey, $settings['answer']['main'])) {
                    $options = implode(', ', array_values($aValue));

                    $data[] = $aKey . (!empty($options) ? ' - ' . $options : '');
                }
            }
        }

        if (isset($settings['additional'])) {
            foreach ($settings['additional'] as $aKey => $aValue) {
                if (!empty($aValue)) {
                    $data[] = $aKey . ': ' . $aValue;
                }
            }
        }

        return implode("\r", $data);
    }

    /**
     * @param $settings
     * @param $types
     * @param $list
     *
     * @return string
     */
    private function parseRequiredFiles($settings, $types, $list)
    {
        $data = [];

        foreach ($settings as $key => $value) {
            if ($value && isset($list[$key]) && isset($types[$list[$key]])) {
                $data[] = $types[$list[$key]];
            }
        }

        return implode("\r", $data);
    }

    /**
     * @param $settings
     * @param $types
     *
     * @return string
     */
    private function parseRequiredFilesLinkId($settings, $types)
    {
        $data = [];

        foreach ($settings as $key => $value) {
            if ($value && isset($types[$value])) {
                $data[] = $types[$value];
            }
        }

        return implode("\r", $data);
    }

    /**
     * @param        $fields
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape_char
     *
     * @return bool|string
     */
    private function createCsvLine($fields, $delimiter = ",", $enclosure = '"', $escape_char = "\\")
    {
        $buffer = fopen('php://temp', 'r+');
        fputcsv($buffer, $fields, $delimiter, $enclosure, $escape_char);
        rewind($buffer);
        $line = fgets($buffer);
        $line = preg_replace("/\r/", "\n", $line);

        fclose($buffer);
        return $line;
    }

    /**
     * @return array
     */
    private function getMap()
    {
        return [
            'required_asset_files'                    => 'Asset filter - required',
            'required_asset_files_required_once'      => 'Asset filter - required once',
            'required_asset_files_visible'            => 'Asset filter - visible',
            'required_asset_files_coil'               => 'Asset coil - required',
            'required_asset_files_coil_required_once' => 'Asset coil - required once',
            'required_asset_files_coil_visible'       => 'Asset coil - visible',
            'required_asset_files_link_id'            => 'Asset link id - required',
            'required_work_order_files'               => 'Work order - required',
            'required_work_order_files_link_id'       => 'Work order link id - required',
        ];
    }
}
