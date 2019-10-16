<?php
/**
 * Created by PhpStorm.
 * User: arvind.chawdhary
 * Date: 2019-10-16
 * Time: 19:08
 */

namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UsersContactInfo extends Model
{
    protected $table = 'users_contact_info';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'first_name', 'last_name', 'mobile_no',
    ];
}