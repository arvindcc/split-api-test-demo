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

class BillIcon extends  Model
{
    use SoftDeletes;

    protected $table = 'bill_icons';

    protected $fillable = ['id', 'name', 'icon_name', 'type', 'icon_bg_color'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function bill(){
        return $this->hasMany('App\Bill');
    }

}