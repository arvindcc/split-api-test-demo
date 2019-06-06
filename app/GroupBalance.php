<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 10/2/19
 * Time: 5:21 AM
 */

namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupBalance extends Model
{
    use SoftDeletes;

    protected $table = 'group_balances';

    protected $fillable = ['id', 'user_owe_id', 'user_paid_id', 'amount', 'group_id'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function group(){
        return $this->belongsTo('App\Group');
    }
}