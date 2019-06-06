<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 20/1/19
 * Time: 8:11 PM
 */

namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use SoftDeletes;

    protected $table = 'groups';

    protected $fillable = ['id', 'user_id', 'group_name', 'icon'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function user(){
        return $this->belongsTo('App\User');
    }

    public function transactions(){
        return $this->hasMany('App\Transaction', 'group_id');
    }

    public function groupUsers(){
        return $this->hasMany('App\GroupUser');
    }

    public function groupBalances(){
        return $this->hasMany('App\GroupBalance');
    }

}