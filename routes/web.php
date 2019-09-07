<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});
$router->post('login',array('uses' => 'Auth\AuthController@login'));
$router->post('getotp',array('uses' => 'Auth\AuthController@getOtp'));
$router->post('otpverify',array('uses' => 'Auth\AuthController@verifyOtp'));
$router->post('signup',array('uses' => 'Auth\AuthController@register'));
$router->post('logout',array('uses' => 'Auth\AuthController@logout'));
$router->post('forgotpassword',array('uses' => 'Auth\AuthController@forgotPassword'));
$router->post('checkversion',array('uses' => 'Auth\AuthController@checkVersion'));

$router->group(['prefix' => 'user'], function () use ($router){
    $router->post('changeavatar',array('uses' => 'User\UserController@updateAvatar'));
    $router->post('accountedit',array('uses' => 'User\UserController@updateAccount'));
    $router->post('changeemail',array('uses' => 'User\UserController@updateEmail'));
    $router->post('changepassword',array('uses' => 'User\UserController@updatePassword'));
    $router->post('userinfo',array('uses' => 'User\UserController@userInfo'));
});

$router->group(['prefix' => 'friend'], function () use ($router){
    $router->post('searchfriends',array('uses' => 'Friend\FriendController@findFriends'));
    $router->post('addfriend',array('uses' => 'Friend\FriendController@addFriend'));
    $router->post('invitefriend',array('uses' => 'Friend\FriendController@inviteFriend'));
    $router->post('deletefriend',array('uses' => 'Friend\FriendController@deleteFriend'));
});

$router->group(['prefix' => 'group'], function () use ($router){
    $router->post('addgroup', array('uses' => 'Group\GroupController@createGroup'));
    $router->post('groupedit', array('uses' => 'Group\GroupController@updateGroupInfo'));
    $router->post('adddeletegroup', array('uses' => 'Group\GroupController@addDeleteGroup'));
});

$router->group(['prefix' => 'sync'], function () use ($router){
    $router->post('shouldsync', array('uses' => 'Sync\SyncController@shouldSync'));
    $router->post('syncuser', array('uses' => 'Sync\SyncController@startSync'));
});
