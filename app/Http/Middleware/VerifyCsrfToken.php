<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifier;

class VerifyCsrfToken extends BaseVerifier
{
    protected $except = [
        // Add routes that should be excluded from CSRF verification
        'api/*', // Example: Exclude all API routes
    ];
}
