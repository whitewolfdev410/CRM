<?php

namespace App\Modules\Service\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\Person\Models\Person;
use App\Modules\PricingStructure\Models\PricingFunction;
use App\Modules\PricingStructure\Models\PricingMatrix;
use App\Modules\Type\Models\Type;
use Illuminate\Support\Facades\DB;

/**
 * @property int     category_type_id
 * @property boolean enabled
 * @property string  long_description
 * @property float   msrp
 * @property string  service_name
 * @property string  short_description
 * @property int     unit
 */
class Service extends LogModel
{
    use TableFixTrait;

    protected $table = 'service';
    protected $primaryKey = 'service_id';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

    protected $fillable
        = [
            'service_name',
            'enabled',
            'short_description',
            'long_description',
            'unit',
            'category_type_id',
            'msrp',
        ];

    // decimals(...,2) should be casted to double to be numbers and not strings
    protected $casts = [
        'msrp' => 'double',
    ];

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        $this->hideNotExistingAttributes();
        $attributes = $this->attributesToArray();

        /*
         * Unit column should be integer in database but for some older
         * databases it could be varchar so we want to cast to int here
         */

        if (isset($attributes['unit'])) {
            $attributes['unit'] = (int)$attributes['unit'];
        }

        /**
         *  Decimal not always visible as float
         */
        if (isset($attributes['msrp'])) {
            $attributes['msrp'] = (float)$attributes['msrp'];
        }

        return array_merge($attributes, $this->relationsToArray());
    }


    /**
     * Return QB ListID for this service. Requires owner if ListID depends on owner
     *
     * @param int|Person $owner Owner ID or owner person, if needed
     * @param int|Person $groupOwnerPersonID Owner ID or owner person, if needed
     *
     * @return string|null QuickBooks ListID for this service for this owner. Null if not found
     */
    public function getQbListId($owner = null, $groupOwnerPersonID = null)
    {
        // TCG uses owner-dependent ListIDs, others don't
        /** @todo add a config option for that instead of hardcoding */
        $hasOwnerDependentListIds = config('app.crm_user') == 'tcg';

        if (!$hasOwnerDependentListIds) {
            // If no Owner Dependent ListIDs, we fetch ListID from quickbooks_sync table
            $listIdRow = DB::table('quickbooks_sync')
                ->where('table_name', 'service')
                ->where('table_id', $this->getId())
                ->first();

            return empty($listIdRow) ? null : $listIdRow->listid;
        } elseif (empty($owner)) {
            // If Owner Dependent ListIDs and no owner -- can't fetch ListID
            return null;
        } else {
            // Accept instance of Person or plain user_id
            if ($owner instanceof Person) {
                $owner = $owner->getPersonId();
                if (empty($owner)) {
                    return null;
                }
            }

            // If Owner Dependent ListIDs, we fetch ListID from quickbooks_sync table using owner
            if (!empty($groupOwnerPersonID)) {
                $listIdRow = DB::table('quickbooks_sync')
                    ->where('table_name', 'service')
                    ->where('table_id', $this->getId())
                    ->where('owner_person_id', $owner)
                    ->where('group_owner_person_id', $groupOwnerPersonID)
                    ->first();
            } else {
                $listIdRow = DB::table('quickbooks_sync')
                    ->where('table_name', 'service')
                    ->where('table_id', $this->getId())
                    ->where('owner_person_id', $owner)
                    ->first();
            }
            return empty($listIdRow) ? null : $listIdRow->listid;
        }
    }



    /**
     * Initialize class and launches table fix
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);
    }

    //region accessors

    /**
     * Get service_name data
     *
     * @return string
     */
    public function getServiceName()
    {
        return $this->service_name;
    }

    /**
     * Get enabled data
     *
     * @return boolean
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * Get short_description data
     *
     * @return string
     */
    public function getShortDescription()
    {
        return $this->short_description;
    }

    /**
     * Get long_description data
     *
     * @return string
     */
    public function getLongDescription()
    {
        return $this->long_description;
    }

    /**
     * Get unit data
     *
     * @return int
     */
    public function getUnit()
    {
        return $this->unit;
    }

    /**
     * Get category_type_id data
     *
     * @return int
     */
    public function getCategoryTypeId()
    {
        return $this->category_type_id;
    }

    /**
     * Get msrp data
     *
     * @return float
     */
    public function getMsrp()
    {
        return $this->msrp;
    }

    //endregion

    //region relationships

    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function unitRel()
    {
        return $this->belongsTo(Type::class, 'unit', 'type_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function categoryTypeRel()
    {
        return $this->belongsTo(Type::class, 'category_type_id', 'type_id');
    }

    /**
     * One-to-many relationship with PricingFunction
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function pricingFunctions()
    {
        return $this->hasMany(PricingFunction::class, 'service_id', 'service_id');
    }

    /**
     * One-to-many relationship with PricingMatrix
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function pricingMatrixes()
    {
        return $this->hasMany(PricingMatrix::class, 'service_id', 'service_id');
    }

    //endregion

    //region scopes

    //endregion
}
