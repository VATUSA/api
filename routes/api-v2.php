<?php
if (env('APP_ENV', 'prod') == "dev") {
    Route::get('llll', function() {
        \Auth::loginUsingId('876594');
        return "OK";
    });
}

Route::group(['middleware' => 'auth:jwt,web', 'prefix' => '/auth'], function() {
    Route::get('token', 'AuthController@getToken');
    Route::get('token/refresh', 'AuthController@getRefreshToken');
    Route::get('info', 'AuthController@getUserInfo');
});
Route::group(['middleware' => 'auth:web,jwt', 'prefix' => '/email'], function () {
    Route::get('/', 'EmailController@getIndex');
    Route::post('/', 'EmailController@postIndex');
});
Route::group(['middleware' => 'auth:web,jwt', 'prefix' => '/exam'], function() {
    Route::post('queue/{id}', 'ExamController@postQueue');
});
Route::group(['middleware' => 'auth:jwt', 'prefix' => '/exam'], function() {
    Route::get('request', 'ExamController@getRequest');
    Route::post('submit', 'ExamController@postSubmit');
});
