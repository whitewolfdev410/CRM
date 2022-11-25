<?php

namespace App\Services;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VerifySeedsService
{
    /**
     * Information
     *
     * @var array
     */
    protected $info = [];

    /**
     * Whether this service was run
     *
     * @var bool
     */
    protected $wasRun = false;

    /**
     * Missing permissions
     *
     * @var array
     */
    protected $missingPermissions = [];

    /**
     * Custom verification rules
     *
     * @var array
     */
    protected $customRules = [];

    /**
     * Get missing permissions
     *
     * @return array
     */
    public function getMissingPermissions()
    {
        if (!$this->wasRun) {
            $this->run();
        }

        return $this->missingPermissions;
    }

    /**
     * Get information
     *
     * @return array
     */
    public function getInfo()
    {
        if (!$this->wasRun) {
            $this->run();
        }

        return $this->info;
    }

    /**
     * Get custom rules
     *
     * @return array
     */
    public function getCustomRules()
    {
        if (!$this->wasRun) {
            $this->run();
        }

        return $this->customRules;
    }

    /**
     * Run this service
     */
    protected function run()
    {
        $seeders = $this->getSeeders();
        $this->missingPermissions = $this->verifyPermissions($seeders);

        $this->wasRun = true;
    }

    /**
     * Adds info
     *
     * @param string $info
     * @param string $type
     */
    protected function addInfo($info, $type)
    {
        $this->info[] = (object)[
            'info' => $info,
            'type' => $type,
        ];
    }

    /**
     * Verify permissions
     *
     * @param array $seeders
     *
     * @return array
     */
    protected function verifyPermissions(array $seeders)
    {
        $missingPermissions = [];

        foreach ($seeders as $index => $seeder) {
            $s = new $seeder();

            $name = mb_substr($seeder, mb_strlen(MODULES_NS));

            // no permission method - save info
            if (!method_exists($s, 'getPermissions')) {
                $this->addInfo("There is no 'getPermissions' method in " .
                    $name . " - this seeder won't be verified", 'warn');
            } else {
                $permissions = $s->getPermissions();

                $moduleMissingPermissions = [];
                foreach ($permissions as $permName => $displayName) {
                    $rec =
                        DB::table('rbac_permissions')->where('name', $permName)
                            ->first();

                    // permission does not exist - add it to missing
                    if (!$rec) {
                        $moduleMissingPermissions[] = (object)[
                            'name' => $permName,
                            'display_name' => $displayName,
                        ];
                    }
                }

                if ($moduleMissingPermissions) {
                    // module has missing permissions - add info that this
                    // module has missing permissions
                    $count = count($moduleMissingPermissions);

                    if ($count == 1) {
                        $this->addInfo($name . ' - there is ' . $count .
                            ' NOT deployed permission', 'error');
                    } else {
                        $this->addInfo($name . ' - there are ' . $count .
                            ' NOT deployed permissions', 'error');
                    }
                } else {
                    // add info that no missing for this module exist
                    $this->addInfo(
                        $name . ' - all permissions were deployed',
                        'info'
                    );
                }

                $missingPermissions =
                    array_merge($missingPermissions, $moduleMissingPermissions);
            }

            $customRules = $this->getSeederCustomRules($s, $name);

            if ($customRules !== false) {
                $this->customRules[] = $customRules;
            }
        }

        return $missingPermissions;
    }

    /**
     * Get seeder custom rules and return them as translated simplified rules
     *
     * @param Seeder $s
     * @param string $name
     *
     * @return bool|object
     * @throws \Exception
     */
    public function getSeederCustomRules($s, $name)
    {
        if (method_exists($s, 'getCustomVerificationRules')) {
            $rules = $s->getCustomVerificationRules();

            return (object)[
                'name' => $name,
                'rules' => $this->getSimpleRules($rules),
            ];
        }

        return false;
    }

    /**
     * Modifies module rules into simplified
     *
     * @param array $rules
     *
     * @return array
     * @throws \Exception
     */
    protected function getSimpleRules(array $rules)
    {
        $output = [];

        foreach ($rules as $rule) {
            $rule = (object)$rule;
            if ($rule->type != 'count') {
                // INFO - in case you want to add custom rules or change code for
                // count, you will need also modify VerifySeeds.php and Seeds.php
                throw new \Exception('Custom rule ' . $rule .
                    ' is not implemented!');
            }
            $table = $rule->table;
            $where = [];
            if (isset($rule->where)) {
                foreach ($rule->where as $field => $value) {
                    $where[] = $field . " = '" . $value . "'";
                }
            }

            $query = "SELECT count(*) AS `nr` FROM {$table}";
            if (count($where)) {
                $query .= ' WHERE ' . implode($where);
            }
            $output[] = (object)[
                'type' => $rule->type,
                'query' => $query,
            ];
        }

        return $output;
    }

    /**
     * Get seeders classes
     *
     * @return array
     */
    public function getSeeders()
    {
        $modulesPath = base_path() . '/app/Modules/';

        $modules = glob($modulesPath . '*/');

        $seeders = [];

        foreach ($modules as $module) {
            $moduleSeeders = glob($module . 'Database/Seeds/*.php');

            foreach ($moduleSeeders as $moduleSeeder) {
                $name =
                    mb_substr(
                        mb_substr($moduleSeeder, mb_strlen($modulesPath)),
                        0,
                        -4
                    );
                $seeders[] =
                    MODULES_NS . str_replace(DIRECTORY_SEPARATOR, '\\', $name);
            }
        }

        return $seeders;
    }
}
