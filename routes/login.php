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
    Route::get("login", "ULSController@getLogin");
    Route::get("redirect", "ULSController@getRedirect");
    Route::get("info", "ULSController@getInfo");

    Route::prefix("/v2")->group(function() {
       Route::get('login', 'ULSv2Controller@getLogin');
       Route::get('redirect', 'ULSv2Controller@getRedirect');
       Route::get('verify', 'ULSv2Controller@getVerify');
    });
});
