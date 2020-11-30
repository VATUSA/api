<?php
if (env('APP_ENV', 'prod') == "dev") {
    Route::get('llll', function () {
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

Route::group(['middleware' => ['private', 'auth:jwt,web'], 'prefix' => '/auth'], function () {
    Route::get('token', 'AuthController@getToken');
    Route::get('token/refresh', 'AuthController@getRefreshToken');
    Route::get('info', 'AuthController@getUserInfo');
});

/******************************************************************************************
 * /bucket
 * AWS S3/IAM Bucket Management
 */

Route::group(['middleware' => ['private', 'auth:jwt,web'], 'prefix' => '/bucket'], function () {
    Route::get('/{facility}', 'BucketController@getBucket')->where('facility', '[A-Z]{3}');
    Route::post('/{facility}', 'BucketController@postBucket')->where('facility', '[A-Z]{3}');
});

/******************************************************************************************
 * /cbt
 * CBT Functions
 */

Route::group(['middleware' => ['public'], 'prefix' => '/cbt'], function () {
    Route::get('/', 'CBTController@getBlocks');
    Route::get('/{id}', 'CBTController@getChapters')->where("id", "[0-9]+");
    Route::group(['middleware' => ['auth:web,jwt']], function () {
        Route::post('/', 'CBTController@postBlock');
        Route::put('/{blockId}', 'CBTController@putBlock');
        Route::delete('/{blockId}', 'CBTController@deleteBlock');

        Route::post('/{blockId}', 'CBTController@postChapter')->where("blockId", "[0-9]+");
        Route::put('/{blockId}/{chapterId}', 'CBTController@putChapter');
        Route::delete('/{blockId}/{chapterId}', 'CBTController@deleteChapter');

    });
});

/******************************************************************************************
 * /email
 * Email functions
 */

Route::group(['middleware' => ['private', 'auth:web,jwt'], 'prefix' => '/email'], function () {
    Route::get('hosted', 'EmailController@getHosted');
    Route::post('hosted/{facility}/{username}', 'EmailController@postHosted');
    Route::delete('hosted/{facility}/{username}', 'EmailController@deleteHosted');

    Route::get('/', 'EmailController@getIndex');
    Route::get('/{address}', 'EmailController@getEmail');
    Route::put('/', 'EmailController@putIndex');
    Route::post('/', 'EmailController@putIndex');  // Alias for now
});

/******************************************************************************************
 * /exam
 * Exam functions
 */

Route::get('/exam', 'ExamController@getExams');
Route::get('/exam/{facility}', 'ExamController@getExams')->where('facility', '[A-Z]{3}');
Route::get('/exam/{id}', 'ExamController@getExambyId')->where('id', '[0-9]+');
Route::group(['middleware' => ['auth:web,jwt', 'private'], 'prefix' => '/exam'], function () {
    Route::get('{id}/questions', 'ExamController@getExamQuestions')->where('id', '[0-9]+');
    Route::put('{id}', 'ExamController@putExam')->where('id', '[0-9]+');
    Route::post('{id}/assign/{cid}', 'ExamController@postExamAssign');
    Route::delete('{id}/assign/{cid}', 'ExamController@deleteExamAssignment');
});
Route::group(['middleware' => ['private', 'auth:jwt,web'], 'prefix' => '/exam'], function () {
    Route::post('queue/{id}', 'ExamController@postQueue');
    Route::get('request', 'ExamController@getRequest');
    Route::post('submit', 'ExamController@postSubmit');
});

/******************************************************************************************
 * /facility
 * Facility functions
 */

Route::get('facility', 'FacilityController@getIndex');
Route::get('facility/{id}', 'FacilityController@getFacility')->where('id', '[A-Za-z]{3}');
Route::get('facility/{id}/roster/{membership?}', 'FacilityController@getRoster')->where('id', '[A-Za-z]{3}');
Route::group(['middleware' => 'auth:web,jwt'], function () {
    Route::put('facility/{id}', 'FacilityController@putFacility')->where('id', '[A-Za-z]{3}');
    Route::delete('facility/{id}/roster/{cid}', 'FacilityController@deleteRoster')->where([
        'id'  => '[A-Za-z]{3}',
        'cid' => '\d+'
    ]);
    Route::put('facility/{id}/transfers/{transferId}', 'FacilityController@putTransfer')->where([
        'id'         => '[A-Za-z]{3}',
        'transferId' => '\d+'
    ]);
    Route::post('facility/{id}/email/{templateName}', 'FacilityController@postEmailTemplate');
});
Route::group(['prefix' => 'facility'], function () {
    Route::get('/', 'FacilityController@getIndex');
    Route::get('{id}', 'FacilityController@getFacility')->where('id', '[A-Za-z]{3}');
    Route::get('{id}/staff', 'FacilityController@getStaff')->where('id', '[A-Za-z]{3}');
    Route::get('{id}/roster', 'FacilityController@getRoster')->where('id', '[A-Za-z]{3}');
    Route::group(['middleware' => 'auth:web,jwt'], function () {
        Route::put('{id}', 'FacilityController@putFacility')->where('id', '[A-Za-z]{3}');
        Route::delete('{id}/roster/{cid}', 'FacilityController@deleteRoster')->where([
            'id'  => '[A-Za-z]{3}',
            'cid' => '\d+'
        ]);
        Route::put('{id}/transfers/{transferId}', 'FacilityController@putTransfer')->where([
            'id'         => '[A-Za-z]{3}',
            'transferId' => '\d+'
        ]);
        Route::post('{id}/email/{templateName}', 'FacilityController@postEmailTemplate');
    });
    Route::group(['middleware' => 'semiprivate'], function () {
        Route::get('{id}/transfers', 'FacilityController@getTransfers')->where('id', '[A-Za-z]{3}');
        Route::get('{id}/email/{templateName}', 'FacilityController@getemailTemplate');
        Route::get('{id}/ulsReturns', 'FacilityController@getUlsReturns');
        Route::post('{id}/ulsReturns', 'FacilityController@addUlsReturn');
        Route::delete('{id}/ulsReturns/{order}', 'FacilityController@removeUlsReturn');
        Route::put('{id}/ulsReturns/{order}', 'FacilityController@putUlsReturn');
        Route::get('{facility}/training/records', 'TrainingController@getFacilityRecords');
        Route::post('{id}/roster/manageVisitor/{cid}', 'FacilityController@addVisitor')->where([
            'id'  => '[A-Za-z]{3}',
            'cid' => '\d+'
        ]);
        Route::delete('{id}/roster/manageVisitor/{cid}', 'FacilityController@removeVisitor')->where([
            'id'  => '[A-Za-z]{3}',
            'cid' => '\d+'
        ]);
    });
});

/******************************************************************************************
 * /infastructure
 * Infastructure commands
 */

Route::group(['prefix' => '/infrastructure', 'middleware' => ['auth:web,jwt', 'private']], function () {
    Route::get('deploy', 'InfrastructureController@deploy');
    Route::post('deploy', 'InfrastructureController@deploy');
});

/******************************************************************************************
 * /solo
 * Statistics functions
 */

Route::group(['prefix' => '/solo'], function () {
    Route::get('/', 'SoloController@getIndex');
    Route::group(['middleware' => 'semiprivate'], function () {
        Route::post('/', 'SoloController@postSolo');
        Route::delete('/', 'SoloController@deleteSolo');
    });
});


/******************************************************************************************
 * /stats
 * Statistics functions
 */

Route::group(['prefix' => '/stats'], function () {
    Route::get('/exams/{facility}', 'StatsController@getExams')->middleware('semiprivate');
});

/******************************************************************************************
 * /support
 * Support functions
 */

Route::group(['prefix' => '/support'], function () {
    Route::get('/support/kb', 'SupportController@getKBs');
    Route::get('/tickets/depts', 'SupportController@getTicketDepts');
    Route::get('/tickets/depts/{dept}/staff', 'SupportController@getTicketDeptStaff');

    Route::group(['middleware' => 'auth:web,jwt'], function () {
        Route::post('/kb', 'SupportController@postKB');
        Route::put('/kb/{id}', 'SupportController@putKB')->where('id', '[0-9]+');
        Route::delete('/kb/{id}', 'SupportController@deleteKB')->where('id', '[0-9]+');

        Route::post('/kb/{categoryid}', 'SupportController@postKBQuestion')
            ->where(['categoryid' => '[0-9]+']);
        Route::put('/kb/{categoryid}/{questionid}', 'SupportController@putKBQuestion')
            ->where(['questionid' => '[0-9]+', 'categoryid' => '[0-9]+']);
        Route::delete('/kb/{categoryid}/{questionid}', 'SupportController@deleteKBQuestion')
            ->where(['questionid' => '[0-9]+', 'categoryid' => '[0-9]+']);
    });
});

/******************************************************************************************
 * /survey
 * Survey functions
 */

Route::group(['prefix' => '/survey', 'middleware' => 'private'], function () {
    Route::get('/{id}', 'SurveyController@getSurvey');
    Route::post('/{id}', 'SurveyController@postSurvey');
    Route::post('/{id}/assign/{cid}', 'SurveyController@postSurveyAssign');
});

/******************************************************************************************
 * /user
 * User functions
 */

Route::group(['prefix' => '/user'], function () {
    Route::get('/filtercid/{partialCid}', 'UserController@filterUsersCid')->where('partialCid', '[0-9]+');
    Route::get('/filterlname/{partialLName}', 'UserController@filterUsersLName')->where('partialLName', '[A-Za-z0-9]+');
    Route::get('/{cid}', 'UserController@getIndex')->where('cid', '[0-9]+');

    Route::get('/roles/{facility}/{role}', 'UserController@getRoleUsers')->where([
        'facility' => '[A-Za-z]{3}',
        'role'     => '[A-Za-z0-9]+'
    ]);
    Route::post('/{cid}/roles/{facility}/{role}', 'UserController@postRole')->where([
        'facility' => '[A-Za-z]{3}',
        'role'     => '[A-Za-z0-9]+'
    ])->middleware('auth:web,jwt');
    Route::delete('/{cid}/roles/{facility}/{role}', 'UserController@deleteRole')->where([
        'facility' => '[A-Za-z]{3}',
        'role'     => '[A-Za-z0-9]+'
    ])->middleware('auth:web,jwt');

    Route::group(['middleware' => 'semiprivate'], function () {
        Route::get('/{cid}/cbt/history', 'UserController@getCBTHistory')->where('cid', '[0-9]+');
        Route::get('/{cid}/cbt/progress/{blockId}', 'UserController@getCBTProgress')->where([
            'cid'     => '[0-9]+',
            'blockId' => '[0-9]+'
        ]);
        Route::put('/{cid}/cbt/progress/{blockId}/{chapterId}', 'UserController@getCBTProgress')->where([
            'cid'       => '[0-9]+',
            'blockId'   => '[0-9]+',
            'chapterId' => '[0-9]+'
        ]);

        Route::get('/{cid}/exam/history', 'UserController@getExamHistory')->where('cid', '[0-9]+');

        Route::get('/{cid}/transfer/checklist', 'UserController@getTransferChecklist')->where('cid', '[0-9]+');
        Route::get('/{cid}/transfer/history', 'UserController@getTransferHistory')->where('cid', '[0-9]+');
        Route::get('/{user}/training/records', 'TrainingController@getUserRecords')->where('user', '[0-9]+');
        Route::post('/{user}/training/record', 'TrainingController@postNewRecord')->where('user', '[0-9]+');
    });

    Route::post('/{cid}/rating', 'UserController@postRating')->where('cid', '[0-9]+')
        ->middleware('auth:web,jwt');
    Route::get('/{cid}/rating/history', 'UserController@getRatingHistory')->where('cid', '[0-9]+')
        ->middleware('semiprivate');

    Route::group(['middleware' => 'private'], function () {
        Route::post('/{cid}/transfer', 'UserController@postTransfer')->where('cid', '[0-9]+');

        Route::get('/{cid}/log', 'UserController@getActionLog')->where('cid', '[0-9]+');
        Route::post('/{cid}/log', 'UserController@postActionLog')->where('cid', '[0-9]+');
        Route::get('/{user}/training/otsEvals', 'TrainingController@getUserOTSEvals')->where('user', '[0-9]+');
        Route::post('/{user}/training/otsEval', 'TrainingController@postOTSEval')->where('user', '[0-9]+');
    });
});

/******************************************************************************************
 * /tmu
 * TMU functions
 */
Route::group(['prefix' => '/tmu'], function () {
    Route::group(['prefix' => '/notice'], function () {
        Route::get('{notice}', 'TMUController@getNotice');
        Route::put('{notice}', 'TMUController@editNotice');
        Route::delete('{notice}', 'TMUController@removeNotice');
    });
    Route::group(['prefix' => '/notices'], function () {
        Route::get('{facility?}', 'TMUController@getNotices');
        Route::group(['middleware' => 'semiprivate'], function () {
            Route::post('/', 'TMUController@addNotice');
        });
    });
});

/******************************************************************************************
 * /training
 * Training functions
 */
Route::group(['prefix' => 'training'], function () {
    Route::group(['middleware' => 'semiprivate'], function () {
        Route::get('record/{record}', 'TrainingController@getTrainingRecord')->where('record', '[0-9]+');
        Route::delete('record/{record}', 'TrainingController@deleteRecord')->where('record', '[0-9]+');
        Route::put('record/{record}', 'TrainingController@editRecord')->where('record', '[0-9]+');
    });
    Route::group(['middleware' => 'private'], function() {
        Route::get('records', 'TrainingController@getAllRecords');
        Route::get('otsEval/{eval}', 'TrainingController@getOTSEval')->where('eval', '[0-9]+');
        Route::get('record/{record}/otsEval', 'TrainingController@getOTSTrainingEval')->where('record', '[0-9]+');
    });
});