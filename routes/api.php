<?php
use Illuminate\Http\Request;

Route::prefix("v2")->namespace("v2")->group(function() {
    Route::middleware("public")->group(function() {
        Route::get("/facility/{all?}", "FacilityController@getIndex")->where("all", "all");
    });
    Route::middleware("private")->group(function() {
        Route::get("auth", "AuthController@getAuth");
    });
});

Route::prefix('v2')->middleware(["apikeyv2"])->namespace('v2')->group(function() {
    require("api-v2.php");
});

/* Allow version 1 to also prefix v1 */
Route::prefix('v1')->namespace("v1")->group(function() {
    require("api-v1.php");
});

/* Assume no version prefix is v1 */
Route::namespace("v1")->group(function() {
    require("api-v1.php");
});

Route::post('/deploy', 'DeployController@getDeploy');

Route::get("/", [
    'as' => 'l5-swagger.api',
    'middleware' => config('l5-swagger.routes.middleware.api', []),
    'uses' => '\L5Swagger\Http\Controllers\SwaggerController@api',
]);
