<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\Exceptions\NestedValidationException;

class VendorsAssignBulkException extends NestedValidationException
{
    /**
     * {@inheritdoc}
     */
    protected $nestedFields = ['vendors'];
}
