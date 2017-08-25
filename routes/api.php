<?php

use Illuminate\Http\Request;

Route::namespace("v2")->group(function() {
    Route::middleware("public-api")->group(function() {
        Route::get("/facility/{all?}", "FacilityController@getIndex")->where("all", "all");
    });
});

Route::prefix('v1')->namespace("v1")->group(function() {

});

Route::post('/deploy', 'DeployController@getDeploy');