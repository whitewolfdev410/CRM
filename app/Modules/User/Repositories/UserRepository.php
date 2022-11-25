<?php

namespace App\Modules\User\Repositories;

use App\Core\AbstractRepository;
use App\Core\User;
use App\Http\Requests\Request;
use App\Modules\System\Services\SystemSettingsService;
use Illuminate\Support\Facades\Cache;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * User repository class
 */
class UserRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [
        'email',
    ];

    /**
     * Columns that might be used for sorting
     *
     * @var array
     */
    protected $sortable = [
        'id',
        'email',
        'person_id',
        'created_at',
        'updated_at',
        'direct_login_token',
    ];

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param User      $user
     */
    public function __construct(
        Container $app,
        User $user
    ) {
        parent::__construct($app, $user);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function show($id, $full = false)
    {
        $output['item'] = $this->find($id);

        if ($full) {
            $output['fields'] = $this->getRequestRules('update');
            $output['roles'] = $this->getUserRoles($id);
            $output['locales'] = $this->config->get('crm_settings.languages', ['en-US']);
        }

        return $output;
    }

    /**
     * Get front-end validation rules
     *
     * @param string $type
     *
     * @return array
     */
    public function getRequestRules($type = 'create')
    {
        $namespace = '\\App\\Modules\\User\\Http\\Requests\\';

        $class = 'UserStoreRequest';

        if ($type === 'update') {
            $class = 'UserUpdateRequest';
        }
        $class = $namespace . $class;

        /** @var Request $req */
        $req = new $class();

        return $req->getFrontendRules();
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     * @throws mixed
     */
    public function create(array $input)
    {
        $input['password'] = Hash::make($input['password']);

        $settingsService
            = new SystemSettingsService($this->makeRepository(
                'SystemSettings',
                'System'
            ), $this);

        $input['gui_settings'] = $settingsService->getList();

        $created = null;
        $roles = null;

        DB::transaction(function () use ($input, &$created, &$roles) {
            /** @var User $created */
            $created = parent::create($input);
            $roles = $this->setUserRoles($created, $input['roles']);

            // clear user auth cache
            $cacheId = 'auth_param_' . $created->id;
            $env = \App::environment();
            Cache::tags($env . '_users')->forget($cacheId);
        });

        return [$created, $roles];
    }

    /**
     * Get configuration - request rules together with user roles
     *
     * @param string $type
     *
     * @return array
     */
    public function getConfig($type)
    {
        $output = $this->getRequestRules($type);
        $output['roles']['data'] = $this->getUserRoles();
        $output['locale']['data'] = $this->config->get('crm_settings.languages', ['en-US']);

        return $output;
    }

    /**
     * Sets roles for given User
     *
     * @param User $user
     * @param      $roles
     *
     * @return mixed
     *
     */
    protected function setUserRoles(User $user, $roles)
    {
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $roles = $this->getValidRoles($roles);

        $rolesToSynchronize = [];

        foreach ($roles as $id => $value) {
            if ($value == 1) {
                $rolesToSynchronize[] = $id;
            }
        }
        $user->roles()->sync($rolesToSynchronize);

        return $this->getUserRoles($user->id);
    }

    /**
     * Get all roles together with indicating if User with $id is assigned
     * to this role (only in case if $id !=0)
     *
     * @param int $id
     *
     * @return mixed
     */
    public function getUserRoles($id = 0)
    {
        $rRepo = $this->makeRepository('Role', 'Permission');

        return $rRepo->getRoles($id);
    }

    /**
     * Get valid array roles from input (choose only existing ids)
     *
     * @param array $roles
     *
     * @return array
     */
    protected function getValidRoles(array $roles)
    {
        if (!$roles) {
            return $roles;
        }

        $rRepo = $this->makeRepository('Role', 'Permission');
        $validIds = array_keys($rRepo->getList());

        $validRoles = array_intersect_key($roles, array_flip($validIds));
        if (!count($validRoles)) {
            $validRoles = array_intersect($roles, $validIds);
        }

        return $validRoles;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     * @throws mixed
     */
    public function updateWithIdAndInput($id, array $input)
    {
        // not allowing to change some fields while update
        if (isset($input['person_id'])) {
            unset($input['person_id']);
        }
        if (isset($input['gui_settings'])) {
            unset($input['gui_settings']);
        }

        // Update password only if not empty
        if ($input['password'] == '') {
            unset($input['password']);
        } else {
            $input['password'] = Hash::make($input['password']);
        }

        $object = null;
        $roles = null;

        DB::transaction(function () use ($id, $input, &$object, &$roles) {
            $object = parent::updateWithIdAndInput($id, $input);
            $roles = $this->setUserRoles($object, $input['roles']);

            // clear user auth cache
            $cacheId = 'auth_param_' . $id;
            $env = \App::environment();
            Cache::tags($env . '_users')->forget($cacheId);
        });

        return [$object, $roles];
    }

    /**
     * Get list of available users
     *
     * @return array
     */
    public function getList()
    {
        return parent::pluck('email', 'id');
    }

    /**
     * Clear cache
     */
    public function clearCache()
    {
        $env = \App::environment();
        Cache::tags($env . '_users')->flush();
    }

    /**
     * Get first user with given $personId
     *
     * @param int $personId
     *
     * @return User
     */
    public function getForPersonId($personId)
    {
        return $this->model->where('person_id', $personId)->first();
    }

    /**
     * Get first user with given $email
     *
     * @param string $email
     *
     * @return User
     */
    public function getForEmail($email)
    {
        return $this->model->where('email', $email)->first();
    }
}
