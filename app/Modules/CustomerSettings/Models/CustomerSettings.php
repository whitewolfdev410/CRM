<?php

namespace App\Modules\CustomerSettings\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\Person\Models\Person;

class CustomerSettings extends LogModel
{
    use TableFixTrait;

    const CREATED_AT = 'created_date';
    
    protected $table = 'customer_settings';
    protected $primaryKey = 'customer_settings_id';

    protected $fillable = [];

    /**
     * Initialize class and launches table fix
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);
    }

    // relationships
    /**
     * Many-to-one relation with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(Person::class, 'company_person_id');
    }
    
    /**
     * One-to-many relation with DocumentSections
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function documentSections()
    {
        return $this->hasMany(
            \App\Modules\CustomerSettings\Models\DocumentSections::class,
            'customer_setting_id'
        );
    }

    // scopes

    // getters

    /**
     * Get company_person_id data
     *
     * @return int
     */
    public function getCompanyPersonId()
    {
        return $this->company_person_id;
    }

    /**
     * Get required_completion_code data
     *
     * @return int
     */
    public function getRequiredCompletionCode()
    {
        return $this->required_completion_code;
    }

    /**
     * Get completion_code_format data
     *
     * @return string
     */
    public function getCompletionCodeFormat()
    {
        return $this->completion_code_format;
    }

    /**
     * Get required_work_order_signature data
     *
     * @return int
     */
    public function getRequiredWorkOrderSignature()
    {
        return $this->required_work_order_signature;
    }

    /**
     * Get ivr_number data
     *
     * @return string
     */
    public function getIvrNumber()
    {
        return $this->ivr_number;
    }

    /**
     * Get footer_file_id data
     *
     * @return int
     */
    public function getFooterFileId()
    {
        return $this->footer_file_id;
    }

    /**
     * Get footer_text data
     *
     * @return string
     */
    public function getFooterText()
    {
        return $this->footer_text;
    }

    /**
     * Get uses_authorization_code data
     *
     * @return int
     */
    public function getUsesAuthorizationCode()
    {
        return $this->uses_authorization_code;
    }

    /**
     * Get auto_generate_work_order_number data
     *
     * @return int
     */
    public function getAutoGenerateWorkOrderNumber()
    {
        return $this->auto_generate_work_order_number;
    }

    /**
     * Get accept_work_order_invitation data
     *
     * @return int
     */
    public function getAcceptWorkOrderInvitation()
    {
        return $this->accept_work_order_invitation;
    }

    /**
     * Get meta_data data
     *
     * @return string
     */
    public function getMetaData()
    {
        return $this->meta_data;
    }

    /**
     *  Get whether work order number is required
     *
     * @return bool
     */
    public function getWorkOrderNumberRequired()
    {
        return !(bool)$this->auto_generate_work_order_number;
    }
}
