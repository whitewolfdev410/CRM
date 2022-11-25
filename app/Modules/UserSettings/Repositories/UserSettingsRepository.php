<?php

namespace App\Modules\UserSettings\Repositories;

use App\Core\AbstractRepository;
use App\Core\Exceptions\CrmSettingsDuplicatedClientKeysException;
use App\Core\Exceptions\DuplicateRecordFoundException;
use App\Modules\Type\Models\Type;
use App\Modules\Type\Repositories\TypeRepository;
use App\Modules\UserSettings\Http\Requests\UserSettingsRequest;
use App\Modules\UserSettings\Models\UserSettings;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * UserSettings repository class
 */
class UserSettingsRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [];

    protected $availableColumns = [
        'id'          => 'user_settings.user_settings_id',
        'person_id'   => 'user_settings.person_id',
        'person_name' => 'person_name(user_settings.person_id)',
        'field_name'  => 'user_settings.field_name',
        'value'       => 'user_settings.value',
        'created_at'  => 'user_settings.created_at',
        'updated_at'  => 'user_settings.updated_at',
    ];

    /**
     * Repository constructor
     *
     * @param  Container  $app
     * @param  UserSettings  $userSettings
     */
    public function __construct(Container $app, UserSettings $userSettings)
    {
        parent::__construct($app, $userSettings);
    }

    public function paginate($perPage = 50, array $columns = ['*'], array $order = [])
    {
        /** @var UserSettings|Builder $model */
        $model = $this->model;

        $input = $this->getInput();

        /*
         * Custom filters, sort, and select created using availableColumns.
         */

        $model = $this->setCustomColumns($model, true, true);
        $model = $this->setCustomSort($model);
        $model = $this->setCustomFilters($model);

        if (empty($input['all'])) {
            $model->where('person_id', Auth::user()->getPersonId());
        }

        /*
         * Paginate with empty $columns, using old paginate.
         */
        $this->setWorkingModel($model);

        $data = parent::paginateSimple($perPage, [], $order);

        $this->clearWorkingModel();

        return $data;
    }

    /**
     * Creates and stores new Model object
     *
     * @param  array  $input
     *
     * @return Model
     */
    public function create(array $input)
    {
        if (empty($input['person_id'])) {
            $input['person_id'] = Auth::user()->getPersonId();
        }
        
        $input['field_name'] = $this->getFieldNameByTypeId($input['type_id']);

        $this->checkIfExists($input['person_id'], $input['type_id']);
        
        return parent::create($input);
    }

    /**
     * Updates Model object identified by given $id with $input data
     *
     * @param  int  $id
     * @param  array  $input
     *
     * @return Model
     */
    public function updateWithIdAndInput($id, array $input)
    {
        $input = ['value' => $input['value'] ?? null];
        
        return parent::updateWithIdAndInput($id, $input);
    }
    
    /**
     * Get front-end validation rules
     *
     * @return array
     */
    public function getRequestRules()
    {
        $req = new UserSettingsRequest();

        return $req->getFrontendRules();
    }

    /**
     * Get value of given field for given person (or for current person
     * if no person given)
     *
     * @param  int  $field
     * @param  int|null  $personId
     *
     * @return string|null
     */
    public function getFieldValue($field, $personId = null)
    {
        if ($personId === null) {
            $personId = getCurrentPersonId();
        }
        $record = $this->model->where('field_name', $field)
            ->whereIn('person_id', [$personId, 0])
            ->orderByDesc('person_id')
            ->first();

        if ($record) {
            return $record->getValue();
        }

        return null;
    }

    /**
     * @param $typeId
     *
     * @return string
     */
    private function getFieldNameByTypeId($typeId)
    {
        $typeKey = explode('.', Type::find($typeId)->type_key);

        return end($typeKey);
    }

    /**
     * @return mixed
     */
    public function getTypes()
    {
        $personId = request()->get('person_id', Auth::user()->getPersonId());
        
        $existingTypeIds = $this->model
            ->where('person_id', $personId)
            ->pluck('type_id')
            ->all();
        
        if (empty($existingTypeIds)) {
            $existingTypeIds = [0];
        }

        return Type::where('type', 'user_settings')
            ->whereNotIn('type_id', $existingTypeIds)
            ->orderBy('orderby')
            ->orderBy('type_value')
            ->get();
    }

    /**
     * @param $personId
     * @param $typeId
     */
    private function checkIfExists($personId, $typeId)
    {
        $result = $this->model
            ->where('person_id', $personId)
            ->where('type_id', $typeId)
            ->first();
        
        if ($result) {
            throw $this->app->make(DuplicateRecordFoundException::class);
        }
    }

    /**
     * Get settings by type
     *
     * @param $type
     *
     * @return mixed
     */
    public function getByType($type)
    {
        $query = $this->model
            ->where('person_id', Auth::user()->getPersonId());
        
        if ((int)$type) {
            $query = $query->where('type_id', $type);
        } else {
            $query = $query->where('field_name', $type);
        }
        
        return $query->first();
    }
}
