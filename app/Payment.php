<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 1/12/18
 * Time: 7:16 PM
 */

namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{

use SoftDeletes;

    protected $table = 'payments';

    protected $fillable = ['id', 'user_owe_id', 'user_paid_id', 'payment_amount', 'icon', 'transaction_id', 'user_friends_id'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function transaction(){
        return $this->belongsTo('App\Transaction');
    }

    public function friend(){
        return $this->belongsTo('App\UserHasFriend', 'user_friends_id');
    }
}
