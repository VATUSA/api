<?php
Route::group(['middleware' => 'auth:jwt,web', 'prefix' => '/auth'], function() {
    Route::get('token', function() {
        $token = \Auth::guard('jwt')->login(\Auth::user());
        return response()->json([
            'token' => $token,
            'expires_in' => \Auth::guard('jwt')->factory()->getTTL() * 60
        ]);
    });
    Route::get('token/refresh', function() {
        $token = \Auth::guard('jwt')->refresh();
        return response()->json([
            'token' => $token,
            'expires_in' => \Auth::guard('jwt')->factory()->getTTL() * 60
        ]);
    });
    Route::get('info', function() {
        return \Auth::user()->toJson();
    });
});
Route::group(['middleware' => 'auth:web,jwt', 'prefix' => '/exam'], function() {
    Route::post('queue/{id}', 'ExamController@postQueue');
});
Route::group(['middleware' => 'auth:jwt', 'prefix' => '/exam'], function() {
    Route::get('request', 'ExamController@getRequest');
    Route::post('submit', 'ExamController@postSubmit');
});
