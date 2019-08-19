<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 1/12/18
 * Time: 4:14 PM
 */

namespace App\Http\Controllers\CustomTraits;

use App\UserHasFriend;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use App\Log as CustomLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;

trait FriendTrait
{
    protected function syncFriends($friends)
    {
        try {
            foreach ($friends as $key1 => $friend) {
                $syncFriend = UserHasFriend::whereIn('user_id', [Auth::user()->id, $friend['userId']])
                    ->whereIn('friend_id',[Auth::user()->id, $friend['userId']])
                    ->first();
                $amount = $syncFriend->user_id == Auth::user()->id ? $friend['amount'] : 0 - $friend['amount'];
                if($syncFriend != null){
                    $syncFriend->update([
                        'amount' => $amount
                    ]);
                }

            }
            return true;
        }catch(\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('syncFriends: '.$exception->getMessage());
        }
    }

}
