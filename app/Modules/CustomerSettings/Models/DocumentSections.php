<?php

namespace App\Modules\CustomerSettings\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

class DocumentSections extends LogModel
{
    use TableFixTrait;

    protected $table = 'document_sections';
    protected $primaryKey = 'id';

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
     * Many-to-one relationship with CustomerSettings
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customerSettings()
    {
        return $this->belongsTo(CustomerSettings::class, 'customer_setting_id', 'customer_settings_id');
    }


    // scopes

    // getters

    /**
     * Get id data
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get customer_setting_id data
     *
     * @return int
     */
    public function getCustomerSettingsId()
    {
        return $this->customer_setting_id;
    }

    /**
     * Get document data
     *
     * @return string
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * Get section data
     *
     * @return string
     */
    public function getSection()
    {
        return $this->section;
    }

    /**
     * Get label data
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Get content data
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Get ordering data
     *
     * @return int
     */
    public function getOrdering()
    {
        return $this->ordering;
    }
}
