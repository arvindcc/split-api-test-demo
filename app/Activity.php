<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17/2/19
 * Time: 2:22 PM
 */

namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use SoftDeletes;

    protected $table = 'activities';

    protected $fillable = ['id', 'activityId', 'activity_type', 'type', 'transaction_id', 'added_by_user_id', 'user_id', 'group_id', 'added_user', 'removed_user'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function transaction(){
        return $this->belongsTo('App\Transaction', 'transaction_id','id', '=');
    }

    public function user(){
        return $this->belongsTo('App\User');
    }

    public function group(){
        return $this->belongsTo('App\Group');
    }
}
