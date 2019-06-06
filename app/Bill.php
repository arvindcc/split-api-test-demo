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

class Bill extends Model
{
    use SoftDeletes;

    protected $table = 'bills';

    protected $fillable = ['id', 'description', 'split_option_type', 'bill_amount', 'bill_icon_id'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];


    public function billIcon(){
        return $this->belongsTo('App\BillIcon');
    }

    public function billUsers(){
        return $this->hasMany('App\BillUser');
    }

    public function billTransactions(){
        return $this->hasMany('App\BillTransaction');
    }


}