<?php

/*
|--------------------------------------------------------------------------
| Login/ULS Routes
|--------------------------------------------------------------------------
|
*/

Route::get('/', 'SSOController@getIndex');
Route::get('/return', 'SSOController@getReturn');
