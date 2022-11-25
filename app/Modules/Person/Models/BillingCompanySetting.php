<?php

namespace App\Modules\Person\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\File\Models\File;
use App\Modules\Type\Models\Type;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BillingCompanySetting extends LogModel
{
    use TableFixTrait;

    protected $table = 'billing_company_settings';
    protected $primaryKey = 'billing_company_settings_id';

    protected $fillable = [
        'billing_company_id',
        'company_id',
        'pricing_structure_id',
        'profile_id',
        'markup_table_id',
        'listid',
        'owner_person_id',
        'created_at',
        'updated_at'
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

    //endregion
}
