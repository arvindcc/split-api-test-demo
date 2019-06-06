<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 29/12/18
 * Time: 9:33 PM
 */

namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserHasFriend extends Model
{
    use SoftDeletes;

    protected $table = 'user_has_friends';

    protected $fillable = ['id', 'user_id', 'friend_id', 'amount'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function user(){
        return $this->belongsTo('App\User','user_id');
    }

    public function transactions(){
        return $this->hasMany('App\Transaction', 'user_friends_id');
    }

    public function payments(){
        return $this->hasMany('App\Payment', 'user_friends_id');
    }

}