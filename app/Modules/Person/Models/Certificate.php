<?php

namespace App\Modules\Person\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\File\Models\File;
use App\Modules\Type\Models\Type;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Certificate extends LogModel
{
    use TableFixTrait;

    protected $table = 'certificate';
    protected $primaryKey = 'certificate_id';

    protected $fillable = [
        'person_id',
        'type_id',
        'expiration_date',
        'amount',
        'additional_insured_wording',
        'waiver_of_subrogation',
        'expired_creates_activity',
        'issue',
        'payment',
    ];

    /**
     * Initialize class and launches table fix
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);
    }

    //region relationships

    /**
     * One to one relationship with File
     *
     * @return HasOne
     */
    public function file()
    {
        return $this->hasOne(File::class, 'table_id', 'certificate_id')
            ->where('table_name', 'certificate');
    }

    /**
     * Many to one relationship with Person
     *
     * @return BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    /**
     * Many to one relationship with Type
     *
     * @return BelongsTo
     */
    public function type()
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    //endregion
}
