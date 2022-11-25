<?php

namespace App\Modules\Person\Models;

use App\Core\Trans;

class CompanyMenu extends PersonMenu
{
    /**
     * Constructor - sets properties
     *
     * @param array $permissions
     * @param array $menu
     * @param string $module
     */
    public function __construct(
        array $permissions,
        array $menu,
        $module = 'company'
    ) {
        parent::__construct($permissions, $menu, $module);
    }
}
