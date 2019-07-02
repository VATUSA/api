<?php

use Faker\Generator as Faker;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(App\User::class, function (Faker $faker) {
    return [
        'cid' => 999,
        'fname' => 'Test',
        'lname' => 'User',
        'email' => $faker->unique()->safeEmail,
        'facility' => 'ZAE',
        'rating' => $faker->numberBetween(1, 5),
        'flag_needbasic' => 0,
        'flag_xferOverride' => 0,
        'flag_homecontroller' => 1,
        'facility_join' => Carbon::now(),
        'certupdate' => 1,
        'flag_broadcastOptedIn' => 1,
        'flag_preventStaffAssign' => 0
    ];
});
