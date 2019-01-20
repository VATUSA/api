<?php

/*
|--------------------------------------------------------------------------
| Login/ULS Routes
|--------------------------------------------------------------------------
|
*/

Route::middleware(['login'])->group(function() {
    Route::get('/', 'SSOController@getIndex');
    Route::get('/return', 'SSOController@getReturn');
});

Route::prefix("/uls")->middleware(['login'])->group(function () {
    Route::prefix("/v2")->group(function() {
       Route::get('login', 'ULSv2Controller@getLogin');
       Route::get('redirect', 'ULSv2Controller@getRedirect');
       Route::get('info', 'ULSv2Controller@getInfo');
    });
});
