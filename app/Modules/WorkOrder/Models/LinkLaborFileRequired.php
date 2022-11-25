<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

/**
 * Class LinkLaborFileRequired
 *
 * @package App\Modules\WorkOrder\Models
 *
 * @property int    link_labor_file_required_id
 * @property int    customer_settings_id
 * @property string inventory_id
 * @property int    required
 * @property boolean view_only
 * @property string color
 * @property string created_at
 * @property string updated_at
 *
 */
class LinkLaborFileRequired extends LogModel
{
    use TableFixTrait;

    protected $table = 'link_labor_file_required';
    protected $primaryKey = 'link_labor_file_required_id';
    protected $connection = 'mysql-utf8';

    protected $fillable = [
        'customer_settings_id',
        'inventory_id',
        'required',
        'view_only',
        'color'
    ];

    /**
     * Initialize class and launches table fix
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);

        parent::__construct($attributes);
    }

    //region accessors

    /**
     * Return link ID
     *
     * @return int
     */
    public function getId()
    {
        return $this->link_labor_file_required_id;
    }

    /**
     * Get customer_settings_id data
     *
     * @return int
     */
    public function getCustomerSettingsId()
    {
        return $this->customer_settings_id;
    }

    /**
     * Return inventory_id data
     *
     * @return string
     */
    public function getInventoryId()
    {
        return $this->inventory_id;
    }

    /**
     * Get color data
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }
    
    /**
     * Get required data
     *
     * @return int
     */
    public function getRequired()
    {
        return $this->required;
    }

    /**
     * Get required data
     *
     * @return int
     */
    public function getViewOnly()
    {
        return $this->view_only;
    }
    
    /**
     * Return created_at data
     *
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Return updated_at data
     *
     * @return string
     */
    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    //endregion
}
