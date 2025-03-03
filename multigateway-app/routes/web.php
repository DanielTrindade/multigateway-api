<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $port = env('PORT', 8000);
    return "Laravel server working at port: $port" ;
});
