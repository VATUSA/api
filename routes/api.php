<?php

use Illuminate\Http\Request;

Route::middleware("public-api")->group(function() {
    Route::get("/facility/{all?}", "FacilityController@getIndex")->where("all", "all");
});
