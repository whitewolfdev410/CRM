<?php

namespace App\Modules\Address\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\AddressIssue\Models\AddressIssue;
use App\Modules\Asset\Models\Asset;
use App\Modules\Contact\Models\Contact;
use App\Modules\Contact\Models\ContactSql;
use App\Modules\Person\Models\LinkPersonCompany;
use App\Modules\Person\Models\Person;
use App\Modules\Type\Models\Type;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * @property string               address_1
 * @property string               address_2
 * @property int                  address_id
 * @property string               address_name
 * @property string               city
 * @property string               coords_accuracy
 * @property string               country
 * @property string               county
 * @property Carbon|string        date_created
 * @property Carbon|string        date_modified
 * @property int                  geocoded
 * @property int                  geocoding_data
 * @property int                  is_default
 * @property int                  is_residential
 * @property string               latitude
 * @property string               longitude
 * @property int                  person_id
 * @property string               state
 * @property int                  type_id
 * @property int                  user_geocoded
 * @property int                  verified
 * @property string               zip_code
 *
 * @property Contact[]|Collection contactsWithType
 * @property Person[]|Collection  personCompaniesWithDetails
 * @property Person[]|Collection  personCompaniesWithDetails2
 *
 * @method Builder|EloquentBuilder|Address address1Contains(string $text, bool $or = false)
 * @method Builder|EloquentBuilder|Address address2Contains(string $text, bool $or = false)
 * @method Builder|EloquentBuilder|Address belongsToCompany(int $companyId)
 * @method Builder|EloquentBuilder|Address isNotDeleted()
 * @method Builder|EloquentBuilder|Address nameContains(string $text, bool $or = false)
 * @method Builder|EloquentBuilder|Address nameStartsWith(string $text, bool $or = false)
 */
class Address extends LogModel
{
    use TableFixTrait;

    protected $table = 'address';
    protected $primaryKey = 'address_id';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

    const NOT_GEOCODED = 0;
    const GEOCODED = 1;
    const GEOCODING_ERROR = 2;
    const GEOCODED_TOO_FAR = 3;
    const WONT_GEOCODE = 4;

    protected $fillable
        = [
            'address_1',
            'address_2',
            'city',
            'county',
            'state',
            'zip_code',
            'country',
            'address_name',
            'person_id',
            'is_default',
            'latitude',
            'longitude',
            'coords_accuracy',
            'geocoded',
            'user_geocoded',
            'geocoding_data',
            'verified',
        ];

    /**
     * Initialize class and launches table fix
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);
        $this->hidden[] = 'is_residential';
        $this->hidden[] = 'type_id';
    }


    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $this->hideNotExistingAttributes();

        $attributes = $this->attributesToArray();

        if (isset($attributes['geocoding_data'])) {
            $attributes['geocoding_data']
                = json_decode($attributes['geocoding_data']);
        }

        $data = array_merge($attributes, $this->relationsToArray());

        // merging relationship
        if (isset($data['person_companies_with_details2'], $data['person_companies_with_details'])
        ) {
            $data['person_companies_with_details']
                = array_merge(
                    $data['person_companies_with_details'],
                    $data['person_companies_with_details2']
                );
            unset($data['person_companies_with_details2']);
        }

        // country and state codes always to upper
        if (isset($data['country'])) {
            $data['country'] = mb_strtoupper($data['country']);
        }

        if (isset($data['state'])) {
            $data['state'] = mb_strtoupper($data['state']);
        }

        return $data;
    }

    //region accessors

    /**
     * Get related person_id for logging in history
     *
     * @return array
     */
    public function getHistoryRelatedRecord()
    {
        if ($this->person_id) {
            return ['person', $this->person_id];
        }

        return null;
    }

    /**
     * Get address_1 field
     *
     * @return string
     */
    public function getAddress1()
    {
        return $this->address_1;
    }

    /**
     * Get address_2 field
     *
     * @return string
     */
    public function getAddress2()
    {
        return $this->address_2;
    }

    /**
     * Get city field
     *
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * Get county field
     *
     * @return string
     */
    public function getCounty()
    {
        return $this->county;
    }

    /**
     * Get state field
     *
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Get zip_code field
     *
     * @return string
     */
    public function getZipCode()
    {
        return $this->zip_code;
    }

    /**
     * Get country field
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * Get address_name field
     *
     * @return string
     */
    public function getAddressName()
    {
        return $this->address_name;
    }

    /**
     * Get person_id field
     *
     * @return int
     */
    public function getPersonId()
    {
        return $this->person_id;
    }

    /**
     * Get type_id field
     *
     * @return int
     */
    public function getTypeId()
    {
        return $this->type_id;
    }

    /**
     * Get is_default field
     *
     * @return int
     */
    public function getIsDefault()
    {
        return $this->is_default;
    }

    /**
     * Get is_residential field
     *
     * @return int
     */
    public function getIsResidential()
    {
        return $this->is_residential;
    }

    /**
     * Get latitude field
     *
     * @return string
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Get longitude field
     *
     * @return string
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * Get coords_accuracy field
     *
     * @return string
     */
    public function getCoordsAccuracy()
    {
        return $this->coords_accuracy;
    }

    /**
     * Get geocoded field
     *
     * @return int
     */
    public function getGeocoded()
    {
        return $this->geocoded;
    }

    /**
     * Get user geocoded field
     *
     * @return int
     */
    public function getUserGeocoded()
    {
        return $this->user_geocoded;
    }

    /**
     * Get geocoding_data field
     *
     * @return int
     */
    public function getGeocodingData()
    {
        return $this->geocoding_data;
    }

    /**
     * Get verified field
     *
     * @return int
     */
    public function getVerified()
    {
        return $this->verified;
    }

    /**
     * Get is_deleted data
     *
     * @return boolean
     */
    public function getIsDeleted()
    {
        return (boolean)$this->is_deleted;
    }

    /**
     * Set is_deleted data
     *
     * @param $value
     *
     * @return $this
     */
    public function setIsDeleted($value)
    {
        $this->is_deleted = $value ? 1 : 0;

        return $this;
    }

    //endregion

    //region relationships

    /**
     * Many-to-one relation with Country
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function countryRel()
    {
        return $this->belongsTo(Country::class, 'country', 'code');
    }

    /**
     * One-to-many relation with LinkPersonCompany
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function personCompanies()
    {
        return $this->hasMany(LinkPersonCompany::class, 'address_id');
    }

    /**
     * One-to-many relation with LinkPersonCompany
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function personCompanies2()
    {
        return $this->hasMany(LinkPersonCompany::class, 'address_id2');
    }

    /**
     * Many-to-one relation with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    /**
     * Many-to-one relation with Person of kind 'company'
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Person::class, 'person_id')
            ->where('kind', '=', 'company');
    }

    /**
     * Many-to-one relation with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function type()
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    /**
     * One-to-many relation with Asset
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function assets()
    {
        return $this->hasMany(Asset::class, 'address_id');
    }

    /**
     * One-to-many relation with Contact
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany|Contact|Collection
     */
    public function contacts()
    {
        /** @var Contact $contacts */
        $contacts = $this->hasMany(Contact::class, 'address_id');

        return $contacts->isNotDeleted();
    }

    /**
     * One-to-many relation with Contact for detailed view
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany|Contact|Collection
     */
    public function contactsWithType()
    {
        return $this->contacts()
            ->leftJoin('type', 'contact.type_id', '=', 'type.type_id')
            ->select('contact.*', 'type.type_key', 'type_value', 'type.color');
    }

    /**
     * One-to-many relation with AddressIssue
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function issues()
    {
        return $this->hasMany(AddressIssue::class, 'address_id');
    }

    /**
     * One-to-many relation with LinkPersonCompany for detailed view.
     * This relationship will be automatically merged with
     * personCompaniesWithDetails2 when data is about to be
     * returned as array or Json
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function personCompaniesWithDetails()
    {
        return $this->hasMany(
            LinkPersonCompany::class,
            'address_id'
        )
            ->join(
                'person AS p',
                'p.person_id',
                '=',
                'link_person_company.member_person_id'
            )
            ->join(
                'person AS me',
                'me.person_id',
                '=',
                'link_person_company.person_id'
            )
            ->selectRaw(
                "
                link_person_company.address_id,
                link_person_company.is_default AS rel_2_is_default,
                link_person_company.is_default2 AS rel_1_is_default,
                'rel_2_is_default' AS is_default_column,
                'rel_1_is_default' AS is_default2_column,
                link_person_company.link_person_company_id,
                link_person_company.address_id AS rel_2_address_id,
                link_person_company.address_id2 AS rel_1_address_id,
                p.person_id AS rel_1_person_id,
                me.person_id AS rel_2_person_id,
                p.kind AS rel_1_kind,
                me.kind AS rel_2_kind,
                p.custom_1 AS rel_1_custom_1,
                p.custom_3 AS rel_1_custom_3,
                p.total_balance AS rel_1_total_balance,
                link_person_company.position AS rel_2_position,
                link_person_company.position2 AS rel_1_position,
                ifnull((select type_value from type where type.type_id = link_person_company.type_id2),'') AS rel_1_relationship,
                ifnull((select type_value from type where type.type_id = link_person_company.type_id),'') AS rel_2_relationship,
                person_name(p.person_id) AS `person_name`,
                ifnull((select type_value from type where type.type_id = p.status_type_id),'') AS rel_1_status_type_value,
                ifnull((select type_value from type where type.type_id = me.status_type_id),'') AS rel_2_status_type_value,
                " . $this->getContactsSql()
            );
    }

    /**
     * One-to-many relation with LinkPersonCompany for detailed view.
     * This relationship will be automatically merged with
     * personCompaniesWithDetails when data is about to be returned as
     * array or Json
     *
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function personCompaniesWithDetails2()
    {
        return $this->hasMany(LinkPersonCompany::class, 'address_id2')
            ->join(
                'person AS p',
                'p.person_id',
                '=',
                'link_person_company.person_id'
            )
            ->join(
                'person AS me',
                'me.person_id',
                '=',
                'link_person_company.member_person_id'
            )
            ->selectRaw(
                "
                link_person_company.address_id2,
                link_person_company.is_default AS rel_1_is_default,
                link_person_company.is_default2 AS rel_2_is_default,
                'rel_1_is_default' AS is_default_column,
                'rel_2_is_default' AS is_default2_column,
                link_person_company.link_person_company_id,
                link_person_company.address_id AS rel_1_address_id,
                link_person_company.address_id2 AS rel_2_address_id,
                p.person_id AS rel_1_person_id,
                me.person_id AS rel_2_person_id,
                p.kind AS rel_1_kind,
                me.kind AS rel_2_kind,
                p.custom_1 AS rel_1_custom_1,
                p.custom_3 AS rel_1_custom_3,
                p.total_balance AS rel_1_total_balance,
                link_person_company.position AS rel_1_position,
                link_person_company.position2 AS rel_2_position,
                ifnull((select type_value from type where type.type_id = link_person_company.type_id),'') AS rel_1_relationship,
                ifnull((select type_value from type where type.type_id = link_person_company.type_id2),'') AS rel_2_relationship,
                person_name(p.person_id) AS `person_name`,
                ifnull((select type_value from type where type.type_id = p.status_type_id),'') AS rel_1_status_type_value,
                ifnull((select type_value from type where type.type_id = me.status_type_id),'') AS rel_2_status_type_value,
                " . $this->getContactsSql()
            );
    }

    /**
     * Generates SQL to get contacts for related persons
     *
     * @return string
     */
    protected function getContactsSql()
    {
        $contact = new ContactSql();

        return $contact->getPhoneWithAddressSql('phone_value', 'p.person_id')
            . ', ' .
            $contact->getFaxWithAddressSql('fax_value', 'p.person_id') . ', ' .
            $contact->getWwwWithAddressSql('www_value', 'p.person_id') . ', ' .
            $contact->getEmailWithAddressSql('email_value', 'p.person_id');
    }

    /**
     * One-to-many relationship with link_vendor_address
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function linkedVendors()
    {
        return $this->hasMany(LinkVendorAddress::class, 'address_id', 'address_id');
    }

    //endregion

    //region scopes

    /**
     * Scope a query to only address whose address_1 contains the given text.
     *
     * @param Builder|EloquentBuilder $query
     * @param string                  $text
     * @param bool                    $or
     *
     * @return Address|EloquentBuilder|Builder
     */
    public function scopeAddress1Contains($query, $text, $or = false)
    {
        return $this->scopeFieldMatches($query, 'address_1', "%$text%", $or);
    }

    /**
     * Scope a query to only address whose address_2 contains the given text.
     *
     * @param Builder|EloquentBuilder $query
     * @param string                  $text
     * @param bool                    $or
     *
     * @return Address|EloquentBuilder|Builder
     */
    public function scopeAddress2Contains($query, $text, $or = false)
    {
        return $this->scopeFieldMatches($query, 'address_2', "%$text%", $or);
    }

    /**
     * Scope a query to only addresses that belong to a company.
     *
     * @param Builder|EloquentBuilder $query
     * @param int                     $companyId
     *
     * @return Address|EloquentBuilder|Builder
     *
     * @throws InvalidArgumentException
     */
    public function scopeBelongsToCompany($query, $companyId)
    {
        return $query->whereHas(
            'company',
            function ($query) use ($companyId) {
                /** @var Builder $query */
                $query->where('person_id', '=', $companyId);
            }
        );
    }

    /**
     * Scope a query to only address whose field matches the given text.
     *
     * @param Builder|EloquentBuilder $query
     * @param string                  $field
     * @param string                  $text
     * @param bool                    $or
     *
     * @return Address|EloquentBuilder|Builder
     */
    public function scopeFieldMatches($query, $field, $text, $or = false)
    {
        return ($or ?
            $query->orWhere("address.$field", 'LIKE', $text) :
            $query->where("address.$field", 'LIKE', $text));
    }

    /**
     * Scope a query to only addresses that are not deleted.
     *
     * @param Builder|EloquentBuilder $query
     *
     * @return Address|EloquentBuilder|Builder
     */
    public function scopeIsNotDeleted($query)
    {
        return $query->where('is_deleted', '=', 0);
    }

    /**
     * Scope a query to only address whose name contains the given text.
     *
     * @param Builder|EloquentBuilder $query
     * @param string                  $text
     * @param bool                    $or
     *
     * @return Address|EloquentBuilder|Builder
     */
    public function scopeNameContains($query, $text, $or = false)
    {
        return $this->scopeFieldMatches($query, 'address_name', "%$text%", $or);
    }

    /**
     * Scope a query to only address whose name starts with the given text.
     *
     * @param Builder|EloquentBuilder $query
     * @param string                  $text
     * @param bool                    $or
     *
     * @return Address|EloquentBuilder|Builder
     */
    public function scopeNameStartsWith($query, $text, $or = false)
    {
        return $this->scopeFieldMatches($query, 'name', "$text%", $or);
    }

    //endregion
}
