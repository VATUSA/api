<?php

/*
|--------------------------------------------------------------------------
| Login/ULS Routes
|--------------------------------------------------------------------------
|
*/

Route::get('/', 'SSOController@getIndex');
Route::get('/return', 'SSOController@getReturn');

Route::prefix("/uls")->group(function () {
    Route::get("login", "ULSController@getLogin");
    Route::get("redirect", "ULSController@getRedirect");
    Route::get("info", "ULSController@getInfo");
});