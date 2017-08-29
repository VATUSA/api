<?php
use Illuminate\Http\Request;

Route::namespace("v2")->group(function() {
    Route::middleware("public-api")->group(function() {
        Route::get("/facility/{all?}", "FacilityController@getIndex")->where("all", "all");
    });
});

Route::prefix('v1')->namespace("v1")->group(function() {
    Route::group(['prefix' => '{apikey}/', 'middleware' => 'apikey'], function () {
        // CBT
        Route::get('cbt/block', 'CBTController@getCBTBlocks');
        Route::get('cbt/block/{id}', 'CBTController@getCBTChapters')->where('id', '[0-9]+');
        Route::get('cbt/chapter/{id}', 'CBTController@getCBTChapter')->where('id', '[0-9]+');
        Route::put('cbt/progress/{cid}', 'CBTController@putCBTProgress')->where('cid', '[0-9]+');

        Route::get('controller/{cid}', 'APIController@getController')->where('cid', '[0-9]+');

        // Exam
        Route::get('exam/results/{cid}', 'ExamController@getUserResults')->where('cid', '[0-9]+');
        Route::get('exam/result/{rid}', 'ExamController@getExamResults')->where('rid', '[0-9]+');

        // Promotion
        Route::get('promotion', 'APIController@getPromotion');
        Route::post('promotion/{cid}', 'APIController@postPromotion')->where('cid', '[0-9]+');

        // Roster
        Route::get('roster', 'FacilityController@getRoster');
        Route::get('roster/{fac}', 'FacilityController@getRoster')->where('fac', '[A-Z]{3}');
        Route::delete('roster/{cid}', 'FacilityController@deleteRoster')->where('cid', '[0-9]+');
        Route::delete('roster/{fac}/{cid}', 'FacilityController@deleteRoster')->where('fac', '[A-Z]{3}')->where('cid', '[0-9]+');

        // Solo Certs
        Route::get('solo/{cid}','APIController@getSolo')->where('cid', '[0-9]+');
        Route::post('solo/{cid}/{position}','APIController@postSolo')->where('cid', '[0-9]+')->where("position", "[0-9A-Z_]+");
        Route::delete('solo/{cid}/{position}','APIController@deleteSolo')->where('cid', '[0-9]+')->where("position", "[0-9A-Z_]+");

        // Transfer
        Route::get('transfer', 'TransferController@getTransfers');
        Route::get('transfer/{fac}', 'TransferController@getTransfers')->where('fac', '[A-Z]{3}');
        Route::post('transfer/{id}', 'TransferController@postTransfer')->where('id', '[0-9]+');

        Route::get('conntest', 'PublicController@getConnectionTest');
    });
    Route::get('news.{ext},{limit}', 'PublicController@getNews')->where(['ext' => '[A-Za-z]+', 'limit' => '\d+']);
    Route::get('news,{limit}', 'PublicController@getNews')->where(['ext' => '[A-Za-z]+', 'limit' => '\d+']);
    Route::get('news.{ext}', 'PublicController@getNews')->where(['ext' => '[A-Za-z]+', 'limit' => '\d+']);
    Route::get('news,{limit}.{ext}', 'PublicController@getNews')->where(['ext' => '[A-Za-z]+', 'limit' => '\d+']);
    Route::get('news', 'PublicController@getNews')->where(['ext' => '[A-Za-z]+', 'limit' => '\d+']);

    Route::get('events.{ext},{limit}', 'PublicController@getEvents')->where(['ext' => '[A-Za-z]+', 'limit' => '\d+']);
    Route::get('events,{limit}', 'PublicController@getEvents')->where(['ext' => '[A-Za-z]+', 'limit' => '\d+']);
    Route::get('events.{ext}', 'PublicController@getEvents')->where(['ext' => '[A-Za-z]+', 'limit' => '\d+']);
    Route::get('events,{limit}.{ext}', 'PublicController@getEvents')->where(['ext' => '[A-Za-z]+', 'limit' => '\d+']);
    Route::get('events', 'PublicController@getEvents')->where(['ext' => '[A-Za-z]+', 'limit' => '\d+']);

    Route::get('roster-{fac}.{ext},{limit}', 'PublicController@getRoster')->where(['fac' => '[A-Z][A-Z][A-Z]', 'ext' => '[A-Za-z]+', 'limit' => '\d+']);
    Route::get('roster-{fac},{limit}', 'PublicController@getRoster')->where(['fac' => '[A-Z][A-Z][A-Z]', 'ext' => '[A-Za-z]+', 'limit' => '\d+']);
    Route::get('roster-{fac}.{ext}', 'PublicController@getRoster')->where(['fac' => '[A-Z][A-Z][A-Z]', 'ext' => '[A-Za-z]+', 'limit' => '\d+']);
    Route::get('roster-{fac},{limit}.{ext}', 'PublicController@getRoster')->where(['fac' => '[A-Z][A-Z][A-Z]', 'ext' => '[A-Za-z]+', 'limit' => '\d+']);
    Route::get('roster-{fac}', 'PublicController@getRoster')->where(['fac' => '[A-Z][A-Z][A-Z]', 'ext' => '[A-Za-z]+', 'limit' => '\d+']);

    Route::get('planes', 'APIController@getPublicPlanes');
});

Route::post('/deploy', 'DeployController@getDeploy');
