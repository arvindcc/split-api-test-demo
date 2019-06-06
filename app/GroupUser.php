<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 20/1/19
 * Time: 9:13 PM
 */

namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupUser extends Model
{
    use SoftDeletes;

    protected $table = 'group_users';

    protected $fillable = ['id', 'user_id', 'group_id', 'user_owe', 'user_paid'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function group(){
        return $this->belongsTo('App\Group');
    }

    public function user(){
        return $this->belongsTo('App\User');
    }
}