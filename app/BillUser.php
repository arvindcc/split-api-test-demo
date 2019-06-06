<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17/2/19
 * Time: 2:07 PM
 */

namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillUser extends Model
{
    use SoftDeletes;

    protected $table = 'bill_users';

    protected $fillable = ['id', 'user_id', 'user_owe', 'user_paid', 'user_in', 'transaction_id', 'bill_id'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function transaction(){
        return $this->belongsTo('App\Transaction');
    }

    public function bill(){
        return $this->belongsTo('App\Bill');
    }

}