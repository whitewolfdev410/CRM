<?php

namespace App\Modules\Person\Models;

use App\Core\Trans;
use Illuminate\Support\Facades\App;

class PersonMenu
{
    /**
     * Array of permissions
     *
     * @var array
     */
    protected $permissions = [];
    /**
     * Array of menu items
     *
     * @var array
     */
    protected $menu = [];

    /**
     * Module name
     *
     * @var string
     */
    protected $module;


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
        $module = 'person'
    ) {
        $this->permissions = $permissions;
        $this->menu = $menu;
        $this->module = $module;
    }

    /**
     * Get menu items based on user permissions
     *
     * @return array
     */
    public function get()
    {
        $trans = App::make(Trans::class);

        $out = [];

        foreach ($this->permissions as $perm => $access) {
            if ($access === false) {
                continue;
            }
            $out[$perm]['url'] = $this->menu[$perm];
            $out[$perm]['label'] = $trans->getAnchor($perm, $this->module);
        }

        return $out;
    }
}
