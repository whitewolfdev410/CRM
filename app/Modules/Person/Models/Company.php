<?php

namespace App\Modules\Person\Models;

class Company extends Person
{
    protected $selectedKind = 'company';

    /**
     * Returns company name
     *
     * @return string
     */
    public function getName()
    {
        return $this->custom_1;
    }
}
