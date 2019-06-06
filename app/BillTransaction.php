<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17/2/19
 * Time: 2:08 PM
 */

namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillTransaction extends Model
{
    use SoftDeletes;

    protected $table = 'bill_transactions';

    protected $fillable = ['id', 'user_owe_id', 'user_paid_id', 'amount', 'transaction_id', 'bill_id'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function transaction(){
        return $this->belongsTo('App\Transaction');
    }

    public function friends(){
        return $this->belongsToMany('App\UserHasFriend');
    }

    public function bill(){
        return $this->belongsTo('App\Bill');
    }
}