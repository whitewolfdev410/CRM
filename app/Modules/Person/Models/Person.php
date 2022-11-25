<?php

namespace App\Modules\Person\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Core\User;
use App\Modules\Activity\Models\Activity;
use App\Modules\Address\Models\Address;
use App\Modules\Contact\Models\Contact;
use App\Modules\Email\Models\Email;
use App\Modules\History\Models\History;
use App\Modules\PricingStructure\Models\PricingStructure;
use App\Modules\System\Models\SystemError;
use App\Modules\Type\Models\Type;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * @property int    assigned_to_person_id
 * @property mixed  commission
 * @property string custom_1
 * @property string custom_2
 * @property string custom_3
 * @property string custom_4
 * @property string custom_5
 * @property string custom_6
 * @property string custom_7
 * @property string custom_8
 * @property string custom_9
 * @property string custom_10
 * @property string custom_11
 * @property string custom_12
 * @property string custom_13
 * @property string custom_14
 * @property string custom_15
 * @property string custom_16
 * @property Carbon dob
 * @property string email
 * @property int    industry_type_id
 * @property int    is_deleted
 * @property string kind
 * @property string last_ip
 * @property string login
 * @property string notes
 * @property int    owner_person_id
 * @property string password
 * @property int    payment_terms_id
 * @property int    perm_group_id
 * @property int    person_id
 * @property int    pricing_structure_id
 * @property int    referral_person_id
 * @property int    rot_type_id
 * @property string salutation
 * @property string sex
 * @property int    status_type_id
 * @property mixed  suspend_invoice
 * @property string token
 * @property Carbon token_time
 * @property float  total_balance
 * @property mixed  total_due_today
 * @property float  total_invoiced
 * @property int    type_id
 *
 * @method Builder|EloquentBuilder|Person customContains(string $text, bool $or = false)
 * @method Builder|EloquentBuilder|Person isClientPortalUser()
 * @method Builder|EloquentBuilder|Person isContact()
 * @method Builder|EloquentBuilder|Person isNotDeleted()
 * @method Builder|EloquentBuilder|Person isTechnician()
 * @method Builder|EloquentBuilder|Person isVendor()
 * @method Builder|EloquentBuilder|Person lastNameStartsWith(string $text, bool $or = false)
 * @method Builder|EloquentBuilder|Person nameContains(string $text, bool $or = false)
 * @method Builder|EloquentBuilder|Person nameContainsWords(string[] $words, bool $or = false)
 * @method Builder|EloquentBuilder|Person nameStartsWith(string $text, bool $or = false)
 */
class Person extends LogModel
{
    use TableFixTrait;

    protected $table = 'person';
    protected $primaryKey = 'person_id';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

    protected $fillable = [];

    protected $selectedKind = 'person';

    /**
     * Initialize class and launches table fix
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $configArray = 'modconfig.' . $this->selectedKind;

        $this->fillable = config($configArray . '.fillable', []);

        $toHide = config($configArray . '.hidden', []);

        $this->hidden = array_unique(array_merge($this->hidden, $toHide));

        $this->initTableFix($attributes);
    }

    //region relationships

    /**
     * Many-to-one relationship with PricingStructure
     *
     * @return BelongsTo|Builder|Collection|PricingStructure
     */
    public function pricingStructure()
    {
        return $this->belongsTo(
            PricingStructure::class,
            'pricing_structure_id',
            'pricing_structure_id'
        );
    }


    /**
     * Many-to-many relationship with Type
     *
     * @return BelongsToMany
     */
    public function groups()
    {
        return $this
            ->belongsToMany(Type::class, 'category', 'table_id', 'type_id')
            ->wherePivot('table_name', '=', 'person');
    }

    /**
     * Many-to-one relation with PaymentTerm
     *
     * @return BelongsTo
     */
    public function paymentTerms()
    {
        // @todo + inverted
        return $this->belongsTo('?', 'payment_terms_id');
    }

    /**
     * Many-to-one relation with Person
     *
     * @return BelongsTo|Builder|Collection|Person
     */
    public function assignedToPerson()
    {
        return $this->belongsTo(__CLASS__, 'assigned_to_person_id');
    }

    /**
     * Many-to-One relation with Type
     *
     * @return BelongsTo|Builder|Collection|Type
     */
    public function type()
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    /**
     * Many-to-One relation with technician Type
     *
     * @return BelongsTo|Builder|Collection|Type
     *
     * @throws InvalidArgumentException
     */
    public function technicianType()
    {
        return $this->type()
            ->where(function ($query) {
                /** @var Builder $query */
                $query->where('type_key', '=', 'person.technician');
            });
    }

    /**
     * Many-to-One relation with vendor Type
     *
     * @return BelongsTo|Builder|Collection|Type
     *
     * @throws InvalidArgumentException
     */
    public function vendorType()
    {
        return $this->type()
            ->where(function ($query) {
                /** @var Builder $query */
                $query->where('type_key', '=', 'company.vendor');
            });
    }

    /**
     * Many-to-One relation with Type
     *
     * @return BelongsTo|Builder|Collection|Type
     */
    public function statusType()
    {
        return $this->belongsTo(Type::class, 'status_type_id');
    }

    /**
     * Many-to-One relation with Type
     *
     * @return BelongsTo|Builder|Collection|Type
     */
    public function industryType()
    {
        return $this->belongsTo(Type::class, 'industry_type_id');
    }

    /**
     * Many-to-One relation with Type
     *
     * @return BelongsTo|Builder|Type
     */
    public function rotType()
    {
        return $this->belongsTo(Type::class, 'rot_type_id');
    }

    /**
     * Many-to-One relation with Person
     *
     * @return BelongsTo|Builder|Person
     */
    public function referralPerson()
    {
        return $this->belongsTo(__CLASS__, 'referral_person_id');
    }

    /**
     * Many-to-One relation with Person
     *
     * @return BelongsTo|Builder|Person
     */
    public function ownerPerson()
    {
        return $this->belongsTo(__CLASS__, 'owner_person_id');
    }

    /**
     * One-To-Many relation with Person
     *
     * @return Builder|Collection|HasMany|Person|Person[]
     */
    public function assignedPersons()
    {
        return $this->hasMany(__CLASS__, 'assigned_to_person_id');
    }

    /**
     * One-To-Many relation with Person
     *
     * @return Builder|Collection|HasMany|Person|Person[]
     */
    public function referredPersons()
    {
        return $this->hasMany(__CLASS__, 'referral_person_id');
    }

    /**
     * One-To-Many relation with Person
     *
     * @return Builder|Collection|HasMany|Person|Person[]
     */
    public function ownedPersons()
    {
        return $this->hasMany(__CLASS__, 'owner_person_id');
    }
    
    /**
     * One-to-many relation with Address
     *
     * @return Address|Address[]|Builder|Collection|HasMany
     */
    public function addresses()
    {
        return $this->hasMany(Address::class, 'person_id');
    }

    /**
     * One-to-One relation with default Address
     *
     * @return Address|Address[]|Builder|Collection|HasMany
     *
     * @throws InvalidArgumentException
     */
    public function defaultAddress()
    {
        return $this->addresses()
            ->where(function ($query) {
                /** @var Builder $query */
                $query->where('is_default', '=', '1');
            });
    }
    
    /**
     * One-to-many relation with Contact
     *
     * @return Builder|Contact|Contact[]|Collection|HasMany
     */
    public function contacts()
    {
        /** @var Contact $contacts */
        $contacts = $this->hasMany(Contact::class, 'person_id');

        return $contacts->isNotDeleted();
    }

    /**
     * One-to-many relation with Contact phones
     *
     * @return Builder|Contact|Contact[]|Collection|HasMany
     */
    public function emailContacts()
    {
        return $this->contacts()
            ->isEmail()
            ->orderByDesc('is_default');
    }

    /**
     * One-to-many relation with Contact phones
     *
     * @return Builder|Contact|Contact[]|Collection|HasMany
     */
    public function phoneContacts()
    {
        return $this->contacts()
            ->isPhone()
            ->orderByDesc('is_default');
    }

    /**
     * One-to-many relation with Activity
     *
     * @return Activity|Activity[]|Builder|Collection|HasMany
     */
    public function activities()
    {
        return $this->hasMany(Activity::class, 'person_id');
    }

    /**
     * One-to-many relation with Activity
     *
     * @return Activity|Activity[]|Builder|Collection|HasMany
     */
    public function createdActivities()
    {
        return $this->hasMany(Activity::class, 'creator_person_id');
    }

    /**
     * One-to-many relation with E-mail
     *
     * @return Builder|Collection|Email|Email[]|HasMany
     */
    public function regardedEmails()
    {
        return $this->hasMany(Email::class, 'regarding_person_id');
    }

    /**
     * One-to-many relation with E-mail
     *
     * @return Builder|Collection|Email|Email[]|HasMany
     */
    public function createdEmails()
    {
        return $this->hasMany(Email::class, 'creator_person_id');
    }

    /**
     * One-to-many relation with E-mail
     *
     * @return Builder|Collection|Email|Email[]|HasMany
     */
    public function emails()
    {
        return $this->hasMany(Email::class, 'person_id');
    }

    /**
     * One-to-many relation with History
     *
     * @return Builder|Collection|History|HasMany
     */
    public function histories()
    {
        return $this->hasMany(History::class, 'person_id');
    }

    /**
     * One-to-many relation with SystemError
     *
     * @return Builder|Collection|HasMany|SystemError|SystemError[]
     */
    public function systemErrors()
    {
        return $this->hasMany(SystemError::class, 'person_id');
    }

    /**
     * Person might have one User account
     *
     * @return Builder|HasOne|User
     */
    public function user()
    {
        return $this->hasOne(User::class, 'person_id', 'person_id');
    }

    /**
     * One-to-many relation with Certificate
     *
     * @return Builder|Certificate|Certificate[]|Collection|HasMany
     */
    public function certificates()
    {
        return $this->hasMany(Certificate::class, 'person_id');
    }

    /**
     * Linked person links when this is a company
     *
     * One-to-many relation with LinkPersonCompany
     *
     * @return Builder|Collection|HasMany|LinkPersonCompany|LinkPersonCompany[]
     */
    public function linkedCompanyPersons()
    {
        return $this->hasMany(LinkPersonCompany::class, 'person_id');
    }

    /**
     * Linked company links when this is a member person
     *
     * One-to-many relation with LinkPersonCompany
     *
     * @return Builder|Collection|HasMany|LinkPersonCompany|LinkPersonCompany[]
     */
    public function linkedPersonCompanies()
    {
        return $this->hasMany(LinkPersonCompany::class, 'member_person_id');
    }

    /**
     * One-to-many relationship with PersonData
     *
     * @return Builder|Collection|HasMany|PersonData|PersonData[]
     */
    public function personData()
    {
        return $this->hasMany(PersonData::class, 'person_id', 'person_id');
    }

    /**
     * Many-to-One relation with contact Type
     *
     * @return BelongsTo|Builder|Collection|Type
     *
     * @throws InvalidArgumentException
     */
    public function contactType()
    {
        return $this->type()
            ->where(function ($query) {
                /** @var Builder $query */
                $query->where('type_key', '=', 'person.contact');
            });
    }

    /**
     * One-to-one relation with Client Portal Person
     *
     * @return Builder|Person|Collection|HasOne
     */
    public function clientPortalPerson()
    {
        /** @var Person $person */
        $person = $this->hasOne(self::class, 'custom_8');

        return $person->isClientPortalUser();
    }

    /**
     * One-to-one relation with Client Portal User
     *
     * @return Builder|User|Collection|HasOne
     */
    public function clientPortalUser()
    {
        /** @var User $user */
        $user = $this->hasOne(User::class, 'company_person_id');

        return $user;
    }

    //endregion

    //region getters

    /**
     * Return custom_1 data
     *
     * @return string
     */
    public function getCustom1()
    {
        return $this->custom_1;
    }

    /**
     * Return custom_2 data
     *
     * @return string
     */
    public function getCustom2()
    {
        return $this->custom_2;
    }

    /**
     * Return custom_3 data
     *
     * @return string
     */
    public function getCustom3()
    {
        return $this->custom_3;
    }

    /**
     * Return custom_4 data
     *
     * @return string
     */
    public function getCustom4()
    {
        return $this->custom_4;
    }

    /**
     * Return custom_5 data
     *
     * @return string
     */
    public function getCustom5()
    {
        return $this->custom_5;
    }

    /**
     * Return custom_6 data
     *
     * @return string
     */
    public function getCustom6()
    {
        return $this->custom_6;
    }

    /**
     * Return custom_7 data
     *
     * @return string
     */
    public function getCustom7()
    {
        return $this->custom_7;
    }

    /**
     * Return custom_8 data
     *
     * @return string
     */
    public function getCustom8()
    {
        return $this->custom_8;
    }

    /**
     * Return custom_9 data
     *
     * @return string
     */
    public function getCustom9()
    {
        return $this->custom_9;
    }

    /**
     * Return custom_10 data
     *
     * @return string
     */
    public function getCustom10()
    {
        return $this->custom_10;
    }

    /**
     * Return custom_11 data
     *
     * @return string
     */
    public function getCustom11()
    {
        return $this->custom_11;
    }

    /**
     * Return custom_12 data
     *
     * @return string
     */
    public function getCustom12()
    {
        return $this->custom_12;
    }

    /**
     * Return custom_13 data
     *
     * @return string
     */
    public function getCustom13()
    {
        return $this->custom_13;
    }

    /**
     * Return custom_14 data
     *
     * @return string
     */
    public function getCustom14()
    {
        return $this->custom_14;
    }

    /**
     * Return custom_15 data
     *
     * @return string
     */
    public function getCustom15()
    {
        return $this->custom_15;
    }

    /**
     * Return custom_16 data
     *
     * @return string
     */
    public function getCustom16()
    {
        return $this->custom_16;
    }

    /**
     * Return sex data
     *
     * @return string
     */
    public function getSex()
    {
        return $this->sex;
    }

    /**
     * Return date of birth
     *
     * @return Carbon
     */
    public function getDob()
    {
        return $this->dob;
    }

    /**
     * Return login data
     *
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * Set login data
     *
     * @param string $value
     */
    public function setLogin($value)
    {
        $this->login = $value;
    }

    /**
     * Return password data
     *
     * @deprecated Use App\Core\User class and its methods
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Sets password data
     *
     * @param string $value
     */
    public function setPassword($value)
    {
        $this->password = $value;
    }

    /**
     * Return email data
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Return pricing_structure_id data
     *
     * @return int
     */
    public function getPricingStructureId()
    {
        return $this->pricing_structure_id;
    }

    /**
     * Return payment_terms_id data
     *
     * @return int
     */
    public function getPaymentTermsId()
    {
        return $this->payment_terms_id;
    }

    /**
     * Return assigned_to_person_id data
     *
     * @return int
     */
    public function getAssignedToPersonId()
    {
        return $this->assigned_to_person_id;
    }

    /**
     * Return perm_group_id data
     *
     * @return int
     */
    public function getPermGroupId()
    {
        return $this->perm_group_id;
    }

    /**
     * Return type_id data
     *
     * @return int
     */
    public function getTypeId()
    {
        return $this->type_id;
    }

    /**
     * Return status_type_id data
     *
     * @return int
     */
    public function getStatusTypeId()
    {
        return $this->status_type_id;
    }

    /**
     * Set status_type_id data
     *
     * @param int $value
     *
     * @return $this
     */
    public function setStatusTypeId($value)
    {
        $this->status_type_id = $value;

        return $this;
    }

    /**
     * Return referral_person_id data
     *
     * @return int
     */
    public function getReferralPersonId()
    {
        return $this->referral_person_id;
    }

    /**
     * Return kind data
     *
     * @return string
     */
    public function getKind()
    {
        return $this->kind;
    }

    /**
     * Return notes data
     *
     * @return string
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Return last_ip data
     *
     * @return string
     */
    public function getLastIp()
    {
        return $this->last_ip;
    }

    /**
     * Return total_balance data
     *
     * @return float
     */
    public function getTotalBalance()
    {
        return $this->total_balance;
    }

    /**
     * Sets total_balance data
     *
     * @param float $value
     */
    public function setTotalBalance($value)
    {
        $this->total_balance = $value;
    }

    /**
     * Return total_invoiced data
     *
     * @return float
     */
    public function getTotalInvoiced()
    {
        return $this->total_invoiced;
    }

    /**
     * Return token data
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Return token_time data
     *
     * @return Carbon
     */
    public function getTokenTime()
    {
        return $this->token_time;
    }

    /**
     * Return owner_person_id data
     *
     * @return int
     */
    public function getOwnerPersonId()
    {
        return $this->owner_person_id;
    }

    /**
     * Return salutation data
     *
     * @return string
     */
    public function getSalutation()
    {
        return $this->salutation;
    }


    /**
     * Return industry_type_id data
     *
     * @return int
     */
    public function getIndustryTypeId()
    {
        return $this->industry_type_id;
    }


    /**
     * Return rot_type_id data
     *
     * @return int
     */
    public function getRotTypeId()
    {
        return $this->rot_type_id;
    }


    /**
     * Return commission data
     *
     * @return mixed
     */
    public function getCommission()
    {
        return $this->commission;
    }


    /**
     * Return total_due_today data
     *
     * @return mixed
     */
    public function getTotalDueToday()
    {
        return $this->total_due_today;
    }


    /**
     * Return suspend_invoice data
     *
     * @return mixed
     */
    public function getSuspendInvoice()
    {
        return $this->suspend_invoice;
    }

    /**
     * Return person name
     *
     * @return string
     */
    public function getName()
    {
        return trim($this->custom_1 . ' ' . $this->custom_3);
    }

    /**
     * Return person_id data
     *
     * @return int
     */
    public function getPersonId()
    {
        return $this->person_id;
    }

    /**
     * Return person Twilio number
     *
     * @return Contact
     *
     * @throws InvalidArgumentException
     */
    public function getTwilioNumber()
    {
        $number = DB::table('contact')
            ->where('name', 'twilio_number')
            ->where('person_id', $this->getPersonId())
            ->first();

        return $number;
    }

    /**
     * Return person's QB ListID. Requires owner if ListID depends on owner
     *
     * @param int|Person $owner Owner ID or owner person, if needed
     *
     * @param null       $companyPersonId
     *
     * @return null|string QuickBooks ListID for this user for this owner. Null if not found
     *
     * @throws InvalidArgumentException
     */
    public function getQbListId($owner = null, $companyPersonId = null)
    {
        // TCG uses owner-dependent ListIDs, others don't
        /** @todo add a config option for that instead of hardcoding */
        $hasOwnerDependentListIds = (config('app.crm_user') === 'tcg') ; // || config('app.crm_user') === 'clm'
        if (!$hasOwnerDependentListIds) {
            if ($companyPersonId > 0) {
                $companyPerson = (new Person())->findOrFail($companyPersonId);

                if ($companyPerson->getTypeId() == getTypeIdByKey('company.vendor') || $companyPerson->getTypeID() == getTypeIdByKey('company.supplier')) {
                    // If no Owner Dependent ListIDs, we fetch ListID from quickbooks_sync table
                    $listIdRow = DB::table('quickbooks_sync')
                        ->where('table_name', 'person')
                        ->where('table_id', $this->getPersonId())
                        ->first();

                    return empty($listIdRow) ? null : $listIdRow->listid;
                }
            }

            $qbLogic = config::get('quickbooks.logic', '');
            if (config('app.crm_user') == 'fs') {
                $listIdRow = DB::table('quickbooks_sync')
                    ->where('table_name', 'person')
                    ->where('table_id', $this->getPersonId())
                    ->first();

                return empty($listIdRow) ? null : $listIdRow->listid;
            } else {
                if ($qbLogic == 'single' || config('app.crm_user') == 'aal') {

                    // If Owner Dependent ListIDs, we fetch ListID from quickbooks_billing table using owner
                    $listIdRow = DB::table('billing_company_settings')
                        ->where('billing_company_id', $this->getPersonId())
                        ->where('company_id', $companyPersonId)
                        ->first();


                    if (empty($listIdRow) || $listIdRow->listid == '') {
                        $listIdRow = DB::table('quickbooks_billing')
                            ->where('billing_company_person_id', $this->getPersonId())
                            ->where('company_person_id', $companyPersonId)
                            ->first();

                        return empty($listIdRow) ? null : $listIdRow->listid;
                    } else {
                        return $listIdRow->listid;
                    }
                }
            }

            // If no Owner Dependent ListIDs, we fetch ListID from quickbooks_sync table
            $listIdRow = DB::table('quickbooks_sync')
                ->where('table_name', 'person')
                ->where('table_id', $this->getPersonId())
                ->first();

            return empty($listIdRow) ? null : $listIdRow->listid;
        }

        if (empty($owner)) {
            // If Owner Dependent ListIDs and no owner -- can't fetch ListID
            return null;
        }

        // Accept instance of Person or plain user_id
        if ($owner instanceof self) {
            $owner = $owner->getPersonId();
            if (empty($owner)) {
                return null;
            }
        }

        if ($companyPersonId) {
            // If Owner Dependent ListIDs, we fetch ListID from quickbooks_billing table using owner, billing company and comapny person
            $listIdRow = DB::table('quickbooks_billing')
                ->where('billing_company_person_id', $this->getPersonId())
                ->where('company_person_id', $companyPersonId)
                ->where('owner_person_id', $owner)
                ->first();
        } else {
            // If Owner Dependent ListIDs, we fetch ListID from quickbooks_billing table using owner
            $listIdRow = DB::table('quickbooks_billing')
                ->where('company_person_id', $this->getPersonId())
                ->where('owner_person_id', $owner)
                ->first();
        }


        return empty($listIdRow) ? null : $listIdRow->listid;
    }

    /**
     * Return person's default email address
     *
     * @return string
     */
    public function getDefaultEmailAddress()
    {
        /** @var Contact $email */
        $email = $this->contacts()
            ->isEmail()
            ->orderByDesc('is_default')
            ->first();

        if ($email !== null) {
            return $email->getValue();
        }

        return null;
    }

    /**
     * Return person's default phone
     *
     * @return string
     */
    public function getDefaultPhone()
    {
        $phone = $this->contacts()
            ->isPhone()
            ->orderByDesc('is_default')
            ->first();

        if ($phone !== null) {
            return $phone->getValue();
        }

        return null;
    }
    
    /**
     * Get is_deleted data
     *
     * @return bool
     */
    public function getIsDeleted()
    {
        return (boolean)$this->is_deleted;
    }

    /**
     * Set is_deleted data
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setIsDeleted($value)
    {
        $this->is_deleted = $value ? 1 : 0;

        return $this;
    }

    //endregion

    //region scopes

    /**
     * Scope a query to only person whose customs (4 -> 16) contains with the given text.
     *
     * @param Builder|EloquentBuilder $query
     * @param string                  $text
     * @param bool                    $or
     *
     * @return Person|EloquentBuilder|Builder
     */
    public function scopeCustomContains($query, $text, $or = false)
    {
        for ($count = 4; $count < 17; $count++) {
            $key = "person.custom_$count";
            $query = ($or
                ? $query->orWhereRaw("$key LIKE ?", ["%$text%"])
                : $query->whereRaw("$key LIKE ?", ["%$text%"]));
        }

        return $query;
    }

    /**
     * Scope a query to only email contact.
     *
     * @param Builder|EloquentBuilder $query
     *
     * @return Contact|Builder|EloquentBuilder
     */
    public function scopeHasEmail($query)
    {
        $typeEmail = Contact::$types['email'];

        return $query
            ->whereExists(function ($innerQuery) use ($typeEmail) {
                /** @var Builder $innerQuery */
                $innerQuery->select(DB::raw(1))
                    ->from('contact')
                    ->join('type', 'contact.type_id', '=', 'type.type_id')
                    ->whereRaw("(type.type_key = '$typeEmail') AND (contact.person_id = person.person_id)");
            });
    }

    /**
     * Scope a query to only non-deleted person.
     *
     * @param EloquentBuilder $query
     *
     * @return Contact|Builder|EloquentBuilder
     */
    public function scopeIsNotDeleted($query)
    {
        return $query->where('person.is_deleted', '=', 0);
    }

    /**
     * Scope a query to only person whose last name starts with the given text.
     *
     * @param EloquentBuilder $query
     * @param string          $text
     * @param bool            $or
     *
     * @return Person|EloquentBuilder|Builder
     */
    public function scopeLastNameStartsWith($query, $text, $or = false)
    {
        return ($or
            ? $query->orWhere('person.custom_2', 'LIKE', "$text%")
            : $query->where('person.custom_2', 'LIKE', "$text%"));
    }

    /**
     * Scope a query to only person whose name contains the given text.
     *
     * @param Builder|EloquentBuilder $query
     * @param string                  $text
     * @param bool                    $or
     *
     * @return Person|EloquentBuilder|Builder
     */
    public function scopeNameContains($query, $text, $or = false)
    {
        return ($or ?
            $query->orWhereRaw('person_name(person.person_id) LIKE ?', ["%$text%"]) :
            $query->whereRaw('person_name(person.person_id) LIKE ?', ["%$text%"]));
    }

    /**
     * Scope a query to only person whose name contains the given words.
     *
     * @param Builder|EloquentBuilder|Person $query
     * @param string[]                       $words
     * @param bool                           $or
     *
     * @return Person|EloquentBuilder|Builder
     *
     * @throws InvalidArgumentException
     */
    public function scopeNameContainsWords($query, $words, $or = false)
    {
        $numbersOfWord = count($words);

        if ($numbersOfWord >= 2) {
            return $query->orWhere(function ($query) use ($words) {
                /** @var Builder $query */
                return $query
                    ->where(function ($query) use ($words) {
                        $text = implode(' ', $words);

                        /** @var Builder $query */
                        return $query
                            ->where('kind', '=', 'company')
                            ->whereRaw('person_name(person.person_id) LIKE ?', ["%$text%"]);
                    })
                    ->orWhere(function ($query) use ($words) {
                        $lastName = '%' . $words[0] . '%';
                        $firstName = '%' . $words[1] . '%';

                        /** @var Builder $query */
                        return $query
                            ->where('kind', '=', 'person')
                            ->where(function ($query) use ($lastName) {
                                /** @var Builder $query */
                                return $query
                                    ->where('custom_1', 'LIKE', $lastName)
                                    ->orWhere('custom_3', 'LIKE', $lastName);
                            })
                            ->where(function ($query) use ($firstName) {
                                /** @var Builder $query */
                                return $query
                                    ->where('custom_1', 'LIKE', $firstName)
                                    ->orWhere('custom_3', 'LIKE', $firstName);
                            });
                    });
            });
        }

        if ($numbersOfWord >= 1) {
            return $this->scopeNameContains($query, $words[0], $or);
        }

        return $query;
    }

    /**
     * Scope a query to only person whose name starts with the given text.
     *
     * @param Builder|EloquentBuilder $query
     * @param string                  $text
     * @param bool                    $or
     *
     * @return Person|EloquentBuilder|Builder
     */
    public function scopeNameStartsWith($query, $text, $or = false)
    {
        return ($or ?
            $query->orWhereRaw('person_name(person.person_id) LIKE ?', ["$text%"]) :
            $query->whereRaw('person_name(person.person_id) LIKE ?', ["$text%"]));
    }

    /**
     * Scope a query to only contact.
     *
     * @param EloquentBuilder $query
     *
     * @return Builder|EloquentBuilder|Person
     */
    public function scopeIsContact($query)
    {
        return $query
            ->has('contactType');
    }

    /**
     * Scope a query to only client portal user.
     *
     * @param EloquentBuilder|Person $query
     *
     * @return Contact|Builder|EloquentBuilder
     */
    public function scopeIsClientPortalUser($query)
    {
        return $query
            ->isContact()
            ->where('custom_1', '=', 'Client Portal User');
    }

    /**
     * Scope a query to only technician.
     *
     * @param EloquentBuilder $query
     *
     * @return Builder|EloquentBuilder|Person
     */
    public function scopeIsTechnician($query)
    {
        return $query
            ->has('technicianType');
    }

    /**
     * Scope a query to only vendor.
     *
     * @param EloquentBuilder $query
     *
     * @return Builder|EloquentBuilder|Person
     */
    public function scopeIsVendor($query)
    {
        return $query
            ->has('vendorType');
    }

    //endregion
}
