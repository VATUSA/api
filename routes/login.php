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
});