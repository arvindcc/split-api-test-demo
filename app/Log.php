<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 13/1/19
 * Time: 11:56 AM
 */

namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Log extends Model
{
    use SoftDeletes;

    protected $table = 'logs';

    protected $fillable = ['id', 'user_id', 'action', 'request_parameter', 'exception', 'status_code'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

}