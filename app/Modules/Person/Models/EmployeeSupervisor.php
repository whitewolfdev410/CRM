<?php
namespace App\Modules\Person\Models;

use App\Core\LogModel;
use Carbon\Carbon;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Class EmployeeSupervisor
 * @package App\Modules\Person\Models
 * @property int $person_employee_supervisor_id
 * @property int $employee_id
 * @property int $supervisor_id
 * @property int $depth
 */
class EmployeeSupervisor extends LogModel
{
    protected $table = 'person_employee_supervisor';
    protected $primaryKey = 'person_employee_supervisor_id';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

    protected $fillable = [
        'employee_id',
        'supervisor_id',
        'depth'
    ];

    protected $selectedKind = 'person_supervisor';
    
    protected $structure;

    //region getters
    /**
     * Get depth of actual position
     */
    public function getDepth()
    {
        return $this->depth;
    }

    /**
     * Get max depth for employee
     * @return mixed
     */
    public function getMaxDepth()
    {
        return EmployeeSupervisor::where('employee_id', $this->employee_id)
            ->max('depth');
    }

    /**
     * Get employee
     * @param $employee_id
     * @return mixed
     */
    public function getEmployee($employee_id)
    {
        return EmployeeSupervisor::where('employee_id', $employee_id)
            ->first();
    }

    /**
     * Get name of employee or supervisor
     * @param string $name
     * @return string
     */
    public function getName($name = 'employee')
    {
        /** @var Person $person */
        if ($name == 'employee') {
            $person = Person::find($this->employee_id);
        } elseif ($name == 'supervisor') {
            $person = Person::find($this->supervisor_id);
        } else {
            throw new InvalidArgumentException('Incorrect name.');
        }
        
        return $person->getName();
    }

    public function getFirstName()
    {
        $person = Person::find($this->employee_id);

        if (!$person) {
            return "Not found #$this->employee_id";
        }

        return $person->custom_1;
    }

    public function getLastName()
    {
        $person = Person::find($this->employee_id);

        if (!$person) {
            return '';
        }

        return $person->custom_3;
    }

    /**
     * Get supervisor id form employee
     * @param $employee_id
     * @return mixed
     */
    public function getSupervisorId($employee_id)
    {
        $supervisor = EmployeeSupervisor::where('employee_id', $employee_id)
            ->where('depth', 0)
            ->first();
        
        if ($supervisor) {
            return $supervisor->supervisor_id;
        } else {
            return null;
        }
    }

    /**
     * Get count of employees for supervisor
     * @param $supervisor_id
     * @return mixed
     */
    public function getEmployeeCount($supervisor_id)
    {
        return EmployeeSupervisor::where('supervisor_id', $supervisor_id)
            ->count();
    }

    /**
     * Get all employees belong to supervisor with depth=0 (not nested)
     * @param int|null $supervisor_id
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function getEmployeesBelongToSupervisor($supervisor_id = null)
    {
        if (is_null($supervisor_id)) {
            throw new InvalidArgumentException('Missing supervisor id.');
        }

        $employees = [];

        $data = EmployeeSupervisor::where('supervisor_id', $supervisor_id)
            ->where('depth', 0)
            ->get();

        /** @var EmployeeSupervisor $employee */
        foreach ($data as $employee) {
            $employees[] = $employee->employee_id;
        }

        return $employees;
    }

    /**
     * Get all employees belong and nested to supervisor
     * @param int|null $supervisor_id
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function getAllEmployeesBelongToSupervisor($supervisor_id = null)
    {
        if (is_null($supervisor_id)) {
            throw new InvalidArgumentException('Missing supervisor id.');
        }

        $employees = [];

        $data = EmployeeSupervisor::where('supervisor_id', $supervisor_id)
            ->get();

        /** @var EmployeeSupervisor $employee */
        foreach ($data as $employee) {
            $employees[] = [
                'employee_id' => $employee->employee_id,
                'name' => $employee->getName(),
                'depth' => $employee->depth
            ];
        }

        return $employees;
    }
    
    /**
     * Get count of employee belong to supervisor
     * @param null $supervisor_id
     * @return int|null
     */
    public function getChildrenCount($supervisor_id = null)
    {
        if (is_null($supervisor_id)) {
            throw new InvalidArgumentException('Missing supervisor id.');
        }
        
        return EmployeeSupervisor::where('supervisor_id', $supervisor_id)->count();
    }

    /**
     * Get list of all children belong to supervisor (not nested)
     * @param null|int $supervisor_id
     * @return array
     */
    public function getChildrenBelongToSupervisor($supervisor_id = null)
    {
        if (is_null($supervisor_id)) {
            throw new InvalidArgumentException('Missing supervisor id.');
        }

        $result = [];
        $children = EmployeeSupervisor::where('supervisor_id', $supervisor_id)
            ->where('depth', 0)
            ->get();

        /** @var EmployeeSupervisor $child */
        foreach ($children as $child) {
            $result[] = $child->employee_id;
        }

        return $result;
    }
    
    /**
     * Get structure of all children even nested for supervisor
     * @param null $supervisor_id
     * @return array
     */
    public function getChildrenStructure($supervisor_id = null)
    {
        if (is_null($supervisor_id)) {
            throw new InvalidArgumentException('Missing supervisor id.');
        }
        
        $children = EmployeeSupervisor::where('supervisor_id', $supervisor_id)
            ->where('depth', 0)
            ->get();
        
        $result = [];
        
        /** @var EmployeeSupervisor $child */
        foreach ($children as $child) {
            $data['employee_id'] = $child->employee_id;
            $data['supervisor_id'] = $child->supervisor_id;
            $data['first_name'] = $child->getFirstName();
            $data['last_name'] = $child->getLastName();
            $data['is_technician'] = $this->isTechnician($child->employee_id);
            $data['children'] = $this->getChildrenStructure($child->employee_id);
            
            $result[] = $data;
        }
        
        return $result;
    }

    /**
     * Get children structure to be display as flatten
     * @param null|int $supervisor_id
     * @return array
     */
    public function getChildrenNameWithFlatten($supervisor_id = null)
    {
        if (is_null($supervisor_id)) {
            throw new InvalidArgumentException('Missing supervisor id.');
        }

        $result = [];
        $children = EmployeeSupervisor::where('supervisor_id', $supervisor_id)
            ->where('depth', 0)
            ->get();

        /** @var EmployeeSupervisor $child */
        foreach ($children as $child) {
            $size = $this->getChildrenCount($child->employee_id);
            $employee = [
                'employee_id' => $child->employee_id,
                'supervisor_id' => $child->supervisor_id,
                'first_name' => $child->getFirstName(),
                'last_name' => $child->getLastName(),
                'is_technician' => $this->isTechnician($child->employee_id),
                'level' => $child->getMaxDepth() + 1,
                'size' => $size
            ];

            $this->structure[] = $employee;
            $result[] = $employee;
            if ($size > 0) {
                $result = $result + $this->getChildrenNameWithFlatten($child->employee_id);
            }
        }

        return $result;
    }

    /**
     * Get structure tree.
     * Function has 2 mode to specify range of build tree. and 2 type of building tree
     * Mode:
     *  - supervisor_id = null => default mode which decelerate build full structure
     *  - supervisor_id is set => optional mode which decelerate build full path for current supervisor (even nesting)
     *
     * Type:
     *  - array - as multidimensional array with data like id, name, depth and children
     *  - select - as array with name of employee preceded with sign '-' which shows depth.
     * Dedicated for Drop-down input
     *
     * @param string $type
     * @param int|null $supervisor_id
     * @return array
     */
    public function getStructure($type = 'array', $supervisor_id = null)
    {
        $result = [];
        $this->structure = [];
        
        if (is_numeric($type)) {
            $supervisor_id = $type;
            $type = 'select';
        }
        
        if (!is_null($supervisor_id) && !is_numeric($supervisor_id)) {
            throw new InvalidArgumentException('Supervisor id must be numeric.');
        }

        switch ($type) {
            case 'select':

                if (is_null($supervisor_id)) {
                    $key = 'person-structure-select';
                    $cachedData = Cache::get($key);

                    if ($cachedData) {
                        $this->structure = $cachedData['structure'];
                        $expiresAt = $cachedData['expiresAt'];
                    } else {
                        $this->getStructureSelect($supervisor_id);

                        $expiresAtDate = Carbon::now()->addMinutes(60);
                        $expiresAt = $expiresAtDate->toW3cString();
                        $cachedData = [
                            'expiresAt'   => $expiresAt,
                            'structure' => $this->structure
                        ];

                        Cache::add($key, $cachedData, 3600);
                    }
                } else {
                    $this->getStructureSelect($supervisor_id);
                }
                
            break;
            case 'array':

                if (is_null($supervisor_id)) {
                    $key = 'person-structure-array';
                    $cachedData = Cache::get($key);

                    if ($cachedData) {
                        $this->structure = $cachedData['structure'];
                        $expiresAt = $cachedData['expiresAt'];
                    } else {
                        $this->getStructureArray($supervisor_id);

                        $expiresAtDate = Carbon::now()->addMinutes(60);
                        $expiresAt = $expiresAtDate->toW3cString();
                        $cachedData = [
                            'expiresAt' => $expiresAt,
                            'structure' => $this->structure
                        ];

                        Cache::add($key, $cachedData, 3600);
                    }
                } else {
                    $this->getStructureArray($supervisor_id);
                }

            break;
        }
        
        return $this->structure;
    }

    /**
     * @param int|null $supervisor_id
     */
    private function getStructureSelect($supervisor_id = null)
    {
        if ($supervisor_id) {
            $rootSupervisors = EmployeeSupervisor::where('supervisor_id', $supervisor_id)->get();
        } else {
            $rootSupervisors = EmployeeSupervisor::where('supervisor_id', 0)->get();
        }

        /** @var EmployeeSupervisor $root */
        foreach ($rootSupervisors as $root) {
            $size = $this->getChildrenCount($root->employee_id);
            $employee = [
                'employee_id' => $root->employee_id,
                'supervisor_id' => 0,
                'first_name' => $root->getFirstName(),
                'last_name' => $root->getLastName(),
                'is_technician' => $this->isTechnician($root->employee_id),
                'level' => 0,
                'size' => $size
            ];

            $this->structure[] = $employee;

            if ($size > 0) {
                $this->getChildrenNameWithFlatten($root->employee_id);
            }
        }
    }

    /**
     * @param int|null $supervisor_id
     */
    private function getStructureArray($supervisor_id = null)
    {
        if ($supervisor_id) {
            $rootSupervisors = EmployeeSupervisor::where('supervisor_id', $supervisor_id)->get();
        } else {
            $rootSupervisors = EmployeeSupervisor::where('supervisor_id', 0)->get();
        }

        /** @var EmployeeSupervisor $root */
        foreach ($rootSupervisors as $root) {
            $employee = [
                'employee_id' => $root->employee_id,
                'supervisor_id' => 0,
                'first_name' => $root->getFirstName(),
                'last_name' => $root->getLastName(),
                'is_technician' => $this->isTechnician($root->employee_id),
                'children' => $this->getChildrenStructure($root->employee_id)
            ];

            $this->structure[] = $employee;
        }
    }
    //endregion
    //region checkers
    
    /**
     * Check if employee has children
     * @param null $supervisor_id
     * @return bool
     */
    public function hasChildren($supervisor_id = null)
    {
        $count = $this->getChildrenCount($supervisor_id);
        return !is_null($count) && $count > 0;
    }

    /**
     * Check if employee is technician
     * @param $employee_id
     * @return bool
     */
    public function isTechnician($employee_id)
    {
        $personTypeId = DB::table('person')
            ->select('type_id')
            ->where('person_id', '=', $employee_id)
            ->first();

        if (!$personTypeId) {
            return false;
        }

        return $personTypeId->type_id == getTypeIdByKey('person.technician');
    }

    /**
     * Get list of employee_id which are in structure, but not exists inside input data.
     * @param $data
     * @return mixed
     */
    public function checkUnusedEmployee($data)
    {
        $employees = DB::table($this->table)
            ->select('employee_id')
            ->distinct('employee_id')
            ->get();

        $result = [];

        foreach ($employees as$employee) {
            $result[] = $employee->employee_id;
        }

        // search unused employee it
        $toDeleteEmployee = array_diff($result, array_keys($data));

        return $toDeleteEmployee;
    }
    
    //endregion

    //region update

    /**
     * Rebuild branch where is employee.
     * Delete all record where exists employee (path to root)
     * Then create new path which is up-to-date
     * @param $employee_id
     * @param $supervisor_id
     * @throws \Exception
     */
    public function updateEmployee($employee_id, $supervisor_id)
    {
        $this->deleteEmployee($employee_id);
        $this->createNewEmployee($supervisor_id, $employee_id);
    }
    
    /**
     * Replace places with parent and child (even nested)
     * @param $employee_id
     * @param $supervisor_id
     * @param null $children
     * @throws \Exception
     */
    public function replaceChildWithParent($employee_id, $supervisor_id, $children = null)
    {
        $parentSupervisorId = $this->getSupervisorId($employee_id);

        $this->deleteEmployee($supervisor_id);
        $this->createNewEmployee($parentSupervisorId, $supervisor_id);
        $children = $this->getChildrenStructure($supervisor_id);
        foreach ($children as $child) {
            $this->updateChildren($supervisor_id, $child['employee_id']);
        }

        $children = $this->getChildrenStructure($employee_id);
        $this->deleteEmployee($employee_id);
        $this->createNewEmployee($supervisor_id, $employee_id);
        foreach ($children as $child) {
            $this->updateChildren($employee_id, $child['employee_id']);
        }
    }

    /**
     * Rebuild structure branch - move all branch in other place
     * @param $employee_id
     * @param $supervisor_id
     * @param null $children
     * @throws \Exception
     */
    public function rebuildBranch($employee_id, $supervisor_id, $children = null)
    {
        $this->deleteEmployee($employee_id);
        $this->createNewEmployee($supervisor_id, $employee_id);

        //Rebuild paths in children to root supervisor
        foreach ($children as $child) {
            // connect child back to parent
            $this->updateChildren($employee_id, $child['employee_id']);
        }
    }
    
    /**
     * Create new nesting to structure.
     * 1. Get full path to root supervisor from actual supervisor of employee
     * 2. Create new employee map to supervisor
     * 3. Create path from new employee to root supervisor
     *
     * @param $supervisor_id
     * @param $employee_id
     */
    public function createNewEmployee($supervisor_id, $employee_id)
    {
        $structure = EmployeeSupervisor::where('employee_id', $supervisor_id)
            ->get();

        EmployeeSupervisor::create([
            'employee_id' => $employee_id,
            'supervisor_id' => $supervisor_id,
            'depth' => 0
        ]);

        /** @var EmployeeSupervisor $row */
        foreach ($structure as $row) {
            if ($row->supervisor_id != 0) {
                EmployeeSupervisor::create([
                    'employee_id' => $employee_id,
                    'supervisor_id' => $row->supervisor_id,
                    'depth' => $row->depth + 1
                ]);
            }
        }
    }

    /**
     * Update children when parent is updated (move to other supervisor)
     * @param $supervisor_id
     * @param $employee_id
     * @throws \Exception
     */
    public function updateChildren($supervisor_id, $employee_id)
    {
        $this->deleteEmployee($employee_id);
        $this->createNewEmployee($supervisor_id, $employee_id);

        foreach ($this->getChildrenStructure($employee_id) as $child) {
            $this->updateChildren($employee_id, $child['employee_id']);
        }
    }

    /**
     * Delete path from employee to root supervisor.
     * Need when employee is deleted from structure or change supervisor
     *
     * @param $employee_id
     * @param string $mode
     * @throws \Exception
     */
    public function deleteEmployee($employee_id, $mode = 'single')
    {
        if ($this->hasChildren($employee_id)) {
            $parentSupervisor = $this->getSupervisorId($employee_id);
            foreach ($this->getChildrenBelongToSupervisor($employee_id) as $child) {
                $this->rebuildBranch($child, $parentSupervisor, $this->getChildrenStructure($child));
            }
        }
        $structure =  EmployeeSupervisor::where('employee_id', $employee_id)
            ->get();
        
        /** @var EmployeeSupervisor $row */
        foreach ($structure as $row) {
            $row->delete();
        }
    }

    /**
     * Update tree with data in array.
     * Method has checking scenario
     * 1. Data is correct
     * 2. Supervisor exists in database
     * 3. Data already exists
     * 4. Employee not exists
     * 5. Employee exists
     * 5.1 Check children
     *  a. Has children
     *      a1. Supervisor is children (even nested)
     *      a2. Supervisor is not children
     *
     * Procedure of realisation scenario is inside function.
     * @param array $data
     * @throws \Exception
     */
    public function updateStructure($data)
    {
        if (is_null($data) || empty($data)) {
            throw new InvalidArgumentException('Missing data to process update structure.');
        }
        
        $created = 0;
        $updated = 0;
        $deleted = 0;
        $incorrect = [];

        $employees = $this->checkUnusedEmployee($data);
        foreach ($employees as $employee) {
            $this->deleteEmployee($employee);
            $deleted++;
        }
        
        foreach ($data as $employee => $supervisor) {
            
            /*
             * Check if data is incorrect
             * - supervisor = employee
             * - employee is 0
             */
            if ($employee == $supervisor) {
                echo "Incorrect data (Supervisor same like employee): $employee" . "\r\n";
                $incorrect[] = "Incorrect data (Supervisor same like employee): $employee.";
                continue;
            } elseif ($employee == 0) {
                echo "Incorrect data (Employee id 0)" . "\r\n";
                $incorrect[] = "Incorrect data (Employee id 0)";
                continue;
            } elseif (!is_numeric($employee) || !is_numeric($supervisor)) {
                echo "Incorrect data (Employee or Supervisor not numeric): $employee -> $supervisor" . "\r\n";
                $incorrect[] = "Incorrect data (Employee or Supervisor not numeric): $employee -> $supervisor";
                continue;
            }
            
            /*
             * Check if this data is already in our database.
             * a. if not exists then create or update (depends on next step)
             * b. if exists - continue (no need to check)
             * c. check if need rebuild path
             */
            $check = EmployeeSupervisor::where('employee_id', $employee)
                ->where('supervisor_id', $supervisor)
                ->where('depth', 0)
                ->first();
            
            if (is_null($check)) {
                
                /*
                 * Check if supervisor is 0, then it means as root employee and need to create if not exists
                 */
                if ($supervisor == 0) {
                    
                    //check if employee does exists in structure due to remove all root
                    $check = EmployeeSupervisor::where('employee_id', $employee)
                        ->first();
                    
                    if (is_null($check)) {
                        EmployeeSupervisor::create([
                            'employee_id' => $employee,
                            'supervisor_id' => 0,
                            'depth' => 0
                        ]);

                        $created++;
                    } else {
                        if ($this->hasChildren($employee)) {
                            $parentSupervisor = $this->getSupervisorId($employee);
                            foreach ($this->getChildrenBelongToSupervisor($employee) as $child) {
                                $this->rebuildBranch($child, $parentSupervisor, $this->getChildrenStructure($child));
                            }
                        }
                        
                        $this->deleteEmployee($employee);
                        EmployeeSupervisor::create([
                            'employee_id' => $employee,
                            'supervisor_id' => 0,
                            'depth' => 0
                        ]);
                        
                        $updated++;
                    }
                    continue;
                }
                
                /*
                 * Before check all scenario we must check if supervisor is in out database
                 */
                $check = EmployeeSupervisor::where('employee_id', $supervisor)
                    ->first();
                
                if (is_null($check)) {
                    echo "Supervisor $supervisor does not exist in structure"."\r\n";
                    $incorrect[] = "Supervisor $supervisor does not exist in structure,";
                    continue;
                }
                
                /*
                 * Moment when we do not have in our database employee to that supervisor, so we need to update structure or create new nest.
                 * First step is to check if we have employee in database.
                 * a. not exists - create new employee in structure with correct nesting
                 * b. exists - need to check position of employee in structure. The results would decelerate next step
                 */
                $check = EmployeeSupervisor::where('employee_id', $employee)
                    ->first();
                
                if (is_null($check)) {
                    /*
                     * Employee does not exists in our database, so we check structure of supervisor to nest new employee in correct place.
                     */
                    $this->createNewEmployee($supervisor, $employee);
                    
                    $created++;
                } else {
                    /*
                     * Employee exists in our database, but with different supervisor -> Need to update structure.
                     * Before rebuild need to check if has children.
                     * a. no children - change structure of employee
                     *  - delete path to root supervisor
                     *  - no need to search records where employee is as supervisor due to no children
                     * b. has children
                     *  - delete path of employee and recreate new path
                     *  - after rebuild get children
                     *      * delete path of child and recreate path to parent
                     */
                    if ($this->hasChildren($employee)) {
                        /*
                         * Employee has children, so need consider some situation
                         * a. new supervisor is child of employee
                         *  - need remember to leave correct structure
                         *  1. change child supervisor for employee supervisor (to prevent possibly crash)
                         *  2. rebuild nested child with new supervisor
                         *  3. add parent to new supervisor (child)
                         *  4. fix path to all employees inside that part of structure, due to changes become incorrect
                         * b. new supervisor is not child from employee
                         */
                        
                        $children = $this->getChildrenBelongToSupervisor($employee);
                        if (in_array($supervisor, $children)) {
                            $this->replaceChildWithParent($employee, $supervisor);
                        } else {
                            $this->rebuildBranch($employee, $supervisor, $this->getChildrenStructure($employee));
                        }
                        
                        $updated++;
                    } else {
                        /*
                         * Employee has no children
                         */
                        $this->updateEmployee($employee, $supervisor);

                        $updated++;
                    }
                }
            } else {
                continue;
            }
        }
        
        echo 'Structure updated' . "\r\n";
        echo "Record incorrect:" . count($incorrect) . "\r\n";
        echo "Record created: $created" . "\r\n";
        echo "Record updated: $updated" . "\r\n";
        echo "Record deleted: $deleted" . "\r\n";
        
        // Check if was any change in structure. If was change need to rebuild cache to have data in cache up-to-date
        if ($created > 0 || $updated > 0 || $deleted > 0) {
            $this->rebuildCache();
        }
    }

    /**
     * Re-build all cache from structure after update tree to be up-to-date.
     * At this moment re-build only full structure
     */
    public function rebuildCache()
    {
        $keys = [
            'select' => 'person-structure-select',
            'array'  => 'person-structure-array'
        ];
        
        echo "Re-building cache of struture" . "\r\n";
        echo "---" . "\r\n";
        
        foreach ($keys as $type => $key) {
            echo "Re-build cache for $type structure" . "\r\n";
            Cache::forget($key);
            $this->getStructure($type);
            echo "Cache $key created" . "\r\n";
        }
        
        echo "---" . "\r\n";
        echo "Cache re-builded" . "\r\n";
    }
    
    //endregion
}
