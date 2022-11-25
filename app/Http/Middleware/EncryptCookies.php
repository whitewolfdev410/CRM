<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;

class EncryptCookies extends \Illuminate\Cookie\Middleware\EncryptCookies
{
    public $except = [
      'user_session'
    ];

    public function __construct(EncrypterContract $encrypter)
    {
        parent::__construct($encrypter);
    }
}
