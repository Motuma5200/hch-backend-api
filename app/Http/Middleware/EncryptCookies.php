<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    // You can add any cookies to the $except array if needed
    protected $except = [
        //
    ];
}
