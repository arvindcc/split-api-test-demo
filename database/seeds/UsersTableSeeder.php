<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 23/12/18
 * Time: 9:22 PM
 */
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{

    public function run()
    {
        // $this->call('UsersTableSeeder');
        // create 10 users using the user factory
        factory(App\User::class, 10)->create();
    }
}