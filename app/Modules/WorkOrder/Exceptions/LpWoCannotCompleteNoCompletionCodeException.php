<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\Exceptions\ValidationException;
use App\Core\Trans;
use Illuminate\Contracts\Container\Container;

class LpWoCannotCompleteNoCompletionCodeException extends ValidationException
{
    public function __construct(Container $app, Trans $trans)
    {
        parent::__construct($app, $trans);
        
        // we set here the same response style as for standard 422 exception
        $this->fields[] = (object)[
            'name' => 'completion_code',
            'messages' => [trans('validation.required')],
            'data' => [],
            'nested' => [],
        ];
    }
}
