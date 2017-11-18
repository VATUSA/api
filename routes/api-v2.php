<?php
if (env('APP_ENV', 'prod') == "dev") {
    Route::get('llll', function() {
        \Auth::loginUsingId('876594');
        return "OK";
    });
}

/******************************************************************************************
 * /auth
 * Auth functions
 */
Route::group(['middleware' => 'auth:jwt,web', 'prefix' => '/auth'], function() {
    Route::get('token', 'AuthController@getToken');
    Route::get('token/refresh', 'AuthController@getRefreshToken');
    Route::get('info', 'AuthController@getUserInfo');
});

/******************************************************************************************
 * /email
 * Email functions
 */
Route::group(['middleware' => 'auth:web,jwt', 'prefix' => '/email'], function () {
    Route::get('/', 'EmailController@getIndex');
    Route::post('/', 'EmailController@postIndex');
});

/******************************************************************************************
 * /exam
 * Exam functions
 */
Route::group(['middleware' => 'auth:web,jwt', 'prefix' => '/exam'], function() {
    Route::post('queue/{id}', 'ExamController@postQueue');
});
Route::group(['middleware' => 'auth:jwt', 'prefix' => '/exam'], function() {
    Route::get('request', 'ExamController@getRequest');
    Route::post('submit', 'ExamController@postSubmit');
});

/******************************************************************************************
 * /facility
 * Facility functions
 */
Route::get('facility', 'FacilityController@getIndex');
Route::get('facility/{id}', 'FacilityController@getFacility')->where('id','[A-Za-z]{3}');
Route::group(['middleware' => 'auth:web,jwt'], function() {
    Route::post('facility/{id}', 'FacilityController@postFacility')->where('id','[A-Za-z]{3}');
});