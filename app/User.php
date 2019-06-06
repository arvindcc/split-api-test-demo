<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Model implements AuthenticatableContract, AuthorizableContract, JWTSubject
{
    use SoftDeletes;

    use Authenticatable, Authorizable;
    protected $table = 'users';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'first_name', 'last_name', 'email', 'mobile_no', 'avatar', 'transaction_count', 'password', 'device_token',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'device_token', 'password',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function friends(){
        return $this->hasMany('App\UserHasFriend');
    }

    public function userAsFriend(){
        return $this->hasMany('App\UserHasFriend', 'friend_id');
    }

    public function allFreinds(){
        return $this->friends->merge($this->userAsFriend);
    }

    public function activities(){
        return $this->hasMany('App\Activity');
    }


    public function groups(){
        return $this->hasMany('App\Group');
    }

    public function groupUsers(){
        return $this->hasMany('App\GroupUser');
    }
}
