<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 1/12/18
 * Time: 5:53 PM
 */

namespace App;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{

use SoftDeletes;

    protected $table = 'transactions';

    protected $fillable = ['id', 'transactionId', 'added_by_user_id', 'note', 'image', 'transaction_type', 'group_id', 'user_friends_id', 'bill_id', 'payment_id'];

    protected $dates = ['added_on', 'created_at', 'updated_at', 'deleted_at'];

    public function group(){
        return $this->belongsTo('App\Group');
    }

    public function friend(){
        return $this->belongsTo('App\UserHasFriend', 'user_friends_id');
    }

    public function bill(){
        return $this->belongsTo('App\Bill', 'bill_id');
    }

    public function payment(){
        return $this->hasMany('App\Payment', 'transaction_id')->withTrashed();
    }

    public function billUsers(){
        return $this->hasMany('App\BillUser', 'transaction_id')->withTrashed();
    }

    public function billTransactions(){
        return $this->hasMany('App\BillTransaction', 'transaction_id')->withTrashed();
    }

}
