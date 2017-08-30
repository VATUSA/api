<?php
use Illuminate\Http\Request;

Route::prefix("v2")->namespace("v2")->group(function() {
    Route::middleware("public-api")->group(function() {
        Route::get("/facility/{all?}", "FacilityController@getIndex")->where("all", "all");
    });
});

Route::prefix('v1')->namespace("v1")->group(function() {
    require("api-v1.php");
});

require("api-v1.php");

Route::post('/deploy', 'DeployController@getDeploy');
