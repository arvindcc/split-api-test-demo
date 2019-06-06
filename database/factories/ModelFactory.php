<?php

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a image model should look.
|
*/

$factory->define(App\User::class, function (Faker\Generator $faker) {
    $name = $faker->name;
    $name = explode(' ', $name);
    return [

        'first_name' => $name[0],
        'last_name' => $name[1],
        'email' => $faker->unique()->email,
        'mobile_no' => $faker->unique()->numberBetween(1111111111, 9999999999),
        'password' => app('hash')->make('secret'),
        'transaction_count' => 0,
    ];
});
