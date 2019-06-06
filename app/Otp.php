<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 25/12/18
 * Time: 5:10 PM
 */

namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Otp extends Model
{
    use SoftDeletes;

    protected $table = 'otps';

    protected $fillable = ['id', 'mobile_no', 'otp'];

    protected $hidden = ['mobile_no', 'otp'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
}