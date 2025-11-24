<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return "This is to test the push on git repository";
});

require __DIR__.'/auth.php';
