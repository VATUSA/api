<?php
if (env('APP_ENV', 'prod') == "dev") {
    Route::get('llll', function() {
        \Auth::loginUsingId(env('DEV_CID_LOGIN'));
        return "OK";
    });
}

/******************************************************************************************
 * /auth
 * Auth functions
 *
 * Private functions, to prevent facility APIs from capturing JWTs
 */
Route::group(['middleware' => ['private','auth:jwt,web'], 'prefix' => '/auth'], function() {
    Route::get('token', 'AuthController@getToken');
    Route::get('token/refresh', 'AuthController@getRefreshToken');
    Route::get('info', 'AuthController@getUserInfo');
});

/******************************************************************************************
 * /email
 * Email functions
 */
Route::group(['middleware' => ['private', 'auth:web,jwt'], 'prefix' => '/email'], function () {
    Route::get('/', 'EmailController@getIndex');
    Route::get('/{address}', 'EmailController@getEmail');
    Route::put('/', 'EmailController@putIndex');
});

/******************************************************************************************
 * /exam
 * Exam functions
 */
Route::group(['middleware' => 'auth:web,jwt', 'prefix' => '/exam'], function() {
    Route::post('queue/{id}', 'ExamController@postQueue');
});
Route::group(['middleware' => ['private','auth:jwt'], 'prefix' => '/exam'], function() {
    Route::get('request', 'ExamController@getRequest');
    Route::post('submit', 'ExamController@postSubmit');
});

/******************************************************************************************
 * /facility
 * Facility functions
 */
Route::get('facility', 'FacilityController@getIndex');
Route::get('facility/{id}', 'FacilityController@getFacility')->where('id','[A-Za-z]{3}');
Route::get('facility/{id}/staff', 'FacilityController@getStaff')->where('id','[A-Za-z]{3}');
Route::get('facility/{id}/roster', 'FacilityController@getRoster')->where('id','[A-Za-z]{3}');
Route::group(['middleware' => 'auth:web,jwt'], function() {
    Route::put('facility/{id}', 'FacilityController@putFacility')->where('id','[A-Za-z]{3}');
});
Route::group(['middleware' => 'auth:web,jwt'], function() {
    Route::delete('facility/{id}/roster/{cid}', 'FacilityController@deleteRoster')->where(['id' => '[A-Za-z]{3}', 'cid' => '\d+']);
    Route::put('facility/{id}/transfers/{transferId}', 'FacilityController@putTransfer')->where(['id' => '[A-Za-z]{3}', 'transferId' => '\d+']);
});
Route::group(['middleware' => 'semiprivate'], function() {
    Route::get('facility/{id}/transfers', 'FacilityController@getTransfers')->where('id', '[A-Za-z]{3}');
});

/******************************************************************************************
 * /stats
 * Statistics functions
 */

Route::group(['prefix' => '/stats'], function() {
    Route::get('/exams/{facility}', 'StatsController@getExams');
});

/******************************************************************************************
 * /users
 * User functions
 */
Route::group(['prefix' => '/users'], function() {
    Route::get('/{cid}', 'UserController@getIndex')->where('cid', '[0-9]+');
    Route::get('/roles/{facility}/{role}', 'UserController@getRoleUsers')->where(['facility' => '[A-Za-z]{3}', 'role' => '[A-Za-z0-9]+']);

    Route::get('/{cid}/cbt/history', 'UserController@getCBTHistory')->where('cid','[0-9]+');
    Route::get('/{cid}/cbt/progress/{blockId}', 'UserController@getCBTProgress')->where(['cid' => '[0-9]+', 'blockId' => '[0-9]+']);
    Route::put('/{cid}/cbt/progress/{blockId}/{chapterId}', 'UserController@getCBTProgress')->where(['cid' => '[0-9]+', 'blockId' => '[0-9]+', 'chapterId' => '[0-9]+']);

    Route::get('/{cid}/transfer/checklist', 'UserController@getTransferChecklist')->where('cid','[0-9]+');
    Route::get('/{cid}/transfer/history', 'UserController@getTransferHistory')->where('cid', '[0-9]+');

    Route::get('/{cid}/rating/history', 'UserController@getRatingHistory')->where('cid','[0-9]+');

    Route::group(['middleware' => 'private'], function() {
        Route::get('/{cid}/log', 'UserController@getActionLog')->where('cid', '[0-9]+');
        Route::post('/{cid}/log','UserController@postActionLog')->where('cid','[0-9]+');
    });
});