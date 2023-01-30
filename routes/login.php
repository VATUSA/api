<?php

/*
|--------------------------------------------------------------------------
| Login Routes
|--------------------------------------------------------------------------
|
*/

Route::middleware(['login'])->group(function() {
    Route::get('/', 'SSOController@getIndex');
    Route::get('/return', 'SSOController@getReturn');
});
