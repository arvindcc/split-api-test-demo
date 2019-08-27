<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 3/2/19
 * Time: 11:01 PM
 */

namespace App\Http\Controllers\Sync;

use App\Activity;
use App\Bill;
use App\Group;
use App\GroupUser;
use App\Http\Controllers\CustomTraits\ActivityTrait;
use App\Http\Controllers\CustomTraits\FriendTrait;
use App\Http\Controllers\CustomTraits\GroupTrait;
use App\Http\Controllers\CustomTraits\UserTrait;
use App\Payment;
use App\Transaction;
use App\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Routing\Controller as BaseController;
use App\Log as CustomLog;


class SyncController extends BaseController
{
    use FriendTrait;
    use ActivityTrait;

    public function __construct(){
        $this->middleware('jwt.auth');
        if(!Auth::guest()){
            $this->user = Auth::user();
        }
    }

    public function shouldSync(Request $request){
        try{
            $this->validate($request, [
                'transactionCount' => 'required',
            ]);
            $shouldSync = '';
            $userTransactionCount = User::where('id', Auth::user()->id)
                                        ->where('transaction_count',$request['transactionCount'])
                                        ->first();
            if($request['transactionCount'] == Auth::user()->transaction_count){
                $shouldSync = false;
                $message = "No need to Sync Data";
            }else{
                $shouldSync = true;
                $message = "Data need to be Sync";
            }
            $status = 200;

        }catch(ValidationException $validationException){
            $data = [
                'errorMessages' => $validationException->errors(),
                'status' => $validationException->status
            ];
            return response()->json($data,200);
        }catch (\Exception $exception){
            $status = 500;
            $message = $exception->getMessage();
            $data = [
                'action' => 'Should Sync',
                'parameters' => $request->all(),
                'message' => $message,
                'status' => 500,
            ];
            Log::critical(json_encode($data, $status));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Should Sync';
            $log['request_parameter'] = json_encode($request->all());
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
        }
        $response = [
            'sync' => $shouldSync,
            'message' => $message,
            'status' => $status
        ];
        return response()->json($response,200);
    }

    public function startSync(Request $request){
        DB::beginTransaction();
        try{
            $friends = array();
            $userData = array();
            $groups = array();
            $activities = array();
            $previousActivity = Activity::where('user_id', Auth::user()->id)
                                        ->orderBy('created_at', 'desc')
                                        ->first();
            $previousActivityId = $previousActivity == null ? 0 : $previousActivity['id'];

            $localCount =  $request['userData']['transactionCount'];
            $localCount = $localCount >= 0 ? $localCount : 0;
            $remoteCount = Auth::user()->transaction_count;

            $activityCount = $localCount > $remoteCount ? $localCount - $remoteCount : $remoteCount - $localCount;
            if($localCount > Auth::user()->transaction_count){
                $count = $localCount + Auth::user()->transaction_count;
                if($request['friends']!=null){
                    $syncedFriends = $this->syncFriends($request['friends']);

                }
                if($request['activities']!= null){
                    $syncedActivities = $this->syncActivity($request['activities']);
                }
                if($request['groups']!=null){
                    $syncedGroups = $this->syncGroup($request['groups']);

                }
            }
            $user = User::where('id', Auth::user()->id)->first();
            $userData = $this->getUserData($user);

            /*$userData['userId']  = $user->id;
            $userData['firstName']  = $user->first_name;
            $userData['lastName']  = $user->last_name;
            $userData['email']  = $user->email;
            $userData['mobile']  = $user->mobile_no;
            $userData['avatar']  = $user->avatar;
            $userData['transactionCount']  = $user->transaction_count;*/

            $userFriends = $user->allFreinds();
            foreach ($userFriends as $key => $userFriend){
                $friendId = $userFriend->friend_id == Auth::user()->id ? $userFriend->user_id : $userFriend->friend_id;
                $friend = User::where('id', $friendId)->first();
                $friends[$key]['userId'] = $friend->id;
                $friendData = $this->getUserData($friend);
                $friends[$key]['firstName'] = $friendData['firstName'];
                $friends[$key]['lastName'] = $friendData['lastName'];
                $friends[$key]['email'] = $friendData['email'];
                $friends[$key]['mobile'] = $friendData['mobile'];
                $friends[$key]['avatar'] = $friendData['avatar'];
                $friends[$key]['transactionCount'] = $friendData['transactionCount'];
                $friends[$key]['amount'] = $userFriend->user_id == Auth::user()->id ? $userFriend->amount : 0 - $userFriend->amount;
                $friends[$key]['transactions'] = $this->formatResponseFriendTransaction($userFriend->transactions);
            }
            $groupIds = GroupUser::select('group_id')->where('user_id', Auth::user()->id)->get();

            $groups = $this->formatResponseGroup(Group::whereIn('id', $groupIds)->get());

            $latestActivities = Activity::where('user_id', Auth::user()->id)
                ->orderBy("created_at", 'desc')
                ->limit($activityCount)
                ->get();
            $activities = $this->formatResponseActivities($latestActivities);
            $message = "Data Sync Successfully";
            $status = 200;

        }catch (\Exception $exception){
            DB::rollBack();
            $status = 500;
            $message = $exception->getMessage();
            $data = [
                'action' => 'Start Sync',
                'message' => $message,
                'status' => 500,
            ];
            Log::critical(json_encode($data, $status));
            DB::beginTransaction();
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Start Sync';
            $log['request_parameter'] = json_encode($request->all());
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
            DB::commit();
        }
        DB::commit();

        $response = [
            'userData' => $userData,
            'friends' => $friends,
            'groups' => $groups,
            'activities' => $activities,
            'message' => $message,
            'status' => $status
        ];
        return response()->json($response,200);
    }

    protected function formatResponseTransaction($transactions){
        try{
            $friendTransactions = array();
            foreach ($transactions as $key => $transaction){
                if($transaction != null ){
                    $friendTransactions[$key]['transactionId'] = $transaction->transactionId;
                    $friendTransactions[$key]['addedOn'] = $transaction->added_on->format('Y-m-d H:i:sP');
                    $friendTransactions[$key]['addedByUserId'] = $transaction->added_by_user_id;
                    $friendTransactions[$key]['date'] = $transaction->created_at->format('Y-m-d H:i:sP');
                    $friendTransactions[$key]['isSynced'] = true;

                    switch ($transaction->transaction_type){
                        case 'payment':
                            $friendTransactions[$key]['type'] = $transaction->transaction_type;
                            $friendTransactions[$key]['amount'] = $transaction->payment->first()->payment_amount;
                            $friendTransactions[$key]['groupId'] = $transaction->group_id;
                            $friendTransactions[$key]['billId'] = $transaction->bill_id;
                            $friendTransactions[$key]['icon'] = $transaction->payment->first()->icon;
                            $friendTransactions[$key]['userOwe'] = $transaction->payment->first()->user_owe_id;
                            $friendTransactions[$key]['userPaid'] = $transaction->payment->first()->user_paid_id;
                            $friendTransactions[$key]['note'] = $transaction->note;
                            $friendTransactions[$key]['image'] = $transaction->image == null ? null : env('APP_URL').env('TRANSACTION_IMAGE_PATH').$transaction->image;
                            break;
                        case 'bill':
                            $bill = Bill::withTrashed()->where('id', $transaction->bill_id)->first();
                            $friendTransactions[$key]['groupId'] = $transaction->group_id;
                            $friendTransactions[$key]['billId'] = $transaction->bill_id;
                            $friendTransactions[$key]['type'] = $transaction->transaction_type;
                            $friendTransactions[$key]['amount'] = $bill->bill_amount;
                            $friendTransactions[$key]['description'] = $bill->description;
                            $friendTransactions[$key]['note'] = $transaction->note;
                            $friendTransactions[$key]['image'] = $transaction->image == null ? null : env('APP_URL').env('TRANSACTION_IMAGE_PATH').$transaction->image;
                            $splitOptionsType = $bill->split_option_type;
                            $friendTransactions[$key]['splitOptionsType'] = $splitOptionsType;
                            $friendTransactions[$key]['billUsers'] = $this->formatResponseBillUsers($transaction->billUsers, $splitOptionsType);
                            $friendTransactions[$key]['transactions'] = $this->formatResponseBillTransactions($transaction->billTransactions);
                            $friendTransactions[$key]['icon'] = $this->formatResponseBillIcon($bill->billIcon);
                            break;
                        default :
                            break;
                    }
                }
            }

            return $friendTransactions;
        }catch (\Exception $exception){
            throw new ModelNotFoundException($exception->getMessage());
        }
    }

    protected function formatResponseFriendTransaction($transactions){
    try{
        $friendTransactions = array();
        $friendGroupIds = array();
        $userGroups = array();
        $iterator = 0;
        foreach ($transactions as $key => $transaction){
            if($transaction != null && $transaction->group_id == null){
                $friendTransactions[$key]['transactionId'] = $transaction->transactionId;
                $friendTransactions[$key]['addedOn'] = $transaction->added_on->format('Y-m-d H:i:sP');
                $friendTransactions[$key]['addedByUserId'] = $transaction->added_by_user_id;
                $friendTransactions[$key]['date'] = $transaction->created_at->format('Y-m-d H:i:sP');
                $friendTransactions[$key]['isSynced'] = true;

                switch ($transaction->transaction_type){
                    case 'payment':
                        $friendTransactions[$key]['type'] = $transaction->transaction_type;
                        $friendTransactions[$key]['amount'] = $transaction->payment->first()->payment_amount;
                        $friendTransactions[$key]['groupId'] = $transaction->group_id;
                        $friendTransactions[$key]['billId'] = $transaction->bill_id;
                        $friendTransactions[$key]['icon'] = $transaction->payment->first()->icon;
                        $friendTransactions[$key]['userOwe'] = $transaction->payment->first()->user_owe_id;
                        $friendTransactions[$key]['userPaid'] = $transaction->payment->first()->user_paid_id;
                        $friendTransactions[$key]['note'] = $transaction->note;
                        $friendTransactions[$key]['image'] = $transaction->image == null ? null : env('APP_URL').env('TRANSACTION_IMAGE_PATH').$transaction->image;
                        break;
                    case 'bill':
                        $bill = Bill::withTrashed()->where('id', $transaction->bill_id)->first();
                        $friendTransactions[$key]['groupId'] = $transaction->group_id;
                        $friendTransactions[$key]['billId'] = $transaction->bill_id;
                        $friendTransactions[$key]['type'] = $transaction->transaction_type;
                        $friendTransactions[$key]['amount'] = $bill->bill_amount;
                        $friendTransactions[$key]['description'] = $bill->description;
                        $friendTransactions[$key]['note'] = $transaction->note;
                        $friendTransactions[$key]['image'] = $transaction->image == null ? null : env('APP_URL').env('TRANSACTION_IMAGE_PATH').$transaction->image;
                        $splitOptionsType = $bill->split_option_type;
                        $friendTransactions[$key]['splitOptionsType'] = $splitOptionsType;
                        $friendTransactions[$key]['billUsers'] = $this->formatResponseBillUsers($transaction->billUsers, $splitOptionsType);
                        $friendTransactions[$key]['transactions'] = $this->formatResponseBillTransactions($transaction->billTransactions);
                        $friendTransactions[$key]['icon'] = $this->formatResponseBillIcon($bill->billIcon);
                        break;
                    default :
                        break;
                }
            }else{
                $friendGroupIds[$iterator] = $transaction->group_id;
                $iterator++;
            }
        }
        $friendGroups = Group::whereIn('id', $friendGroupIds)->get();
        foreach ($friendGroups as $key => $friendGroup){
            $userGroups[$key]['groupId'] = $friendGroup['id'];
            $userGroups[$key]['groupName'] = $friendGroup['group_name'];
            $userGroups[$key]['date'] = $friendGroup['updated_at']->format('Y-m-d H:i:sP');
            $userGroups[$key]['icon'] = $friendGroup['icon'];
            $userGroups[$key]['type'] = 'group';
            $userGroups[$key]['addedOn'] = $friendGroup['created_at']->format('Y-m-d H:i:sP');
        }
        return array_merge($friendTransactions, $userGroups);
    }catch (\Exception $exception){
        throw new ModelNotFoundException($exception->getMessage());
    }
}

    protected function formatResponseGroupTransaction($transactions){
        try{
            $friendTransactions = array();
            $iterator = 0;
            $previousTransaction = 0;
            foreach ($transactions as $key => $transaction ){
                if($transaction != null && $previousTransaction != $transaction->transactionId){
                    $previousTransaction = $transaction->transactionId;
                    $friendTransactions[$iterator]['transactionId'] = $transaction->transactionId;
                    $friendTransactions[$iterator]['addedOn'] = $transaction->added_on->format('Y-m-d H:i:sP');
                    $friendTransactions[$iterator]['addedByUserId'] = $transaction->added_by_user_id;
                    $friendTransactions[$iterator]['date'] = $transaction->created_at->format('Y-m-d H:i:sP');
                    $friendTransactions[$iterator]['isSynced'] = true;

                    switch ($transaction->transaction_type){
                        case 'payment':
                            $friendTransactions[$iterator]['type'] = $transaction->transaction_type;
                            $friendTransactions[$iterator]['amount'] = $transaction->payment->first()->payment_amount;
                            $friendTransactions[$iterator]['groupId'] = $transaction->group_id;
                            $friendTransactions[$iterator]['billId'] = $transaction->bill_id;
                            $friendTransactions[$iterator]['icon'] = $transaction->payment->first()->icon;
                            $friendTransactions[$iterator]['userOwe'] = $transaction->payment->first()->user_owe_id;
                            $friendTransactions[$iterator]['userPaid'] = $transaction->payment->first()->user_paid_id;
                            $friendTransactions[$iterator]['note'] = $transaction->note;
                            $friendTransactions[$iterator]['image'] = $transaction->image;
                            break;
                        case 'bill':
                            $bill = Bill::withTrashed()->where('id', $transaction->bill_id)->first();
                            $friendTransactions[$iterator]['groupId'] = $transaction->group_id;
                            $friendTransactions[$iterator]['billId'] = $transaction->bill_id;
                            $friendTransactions[$iterator]['type'] = $transaction->transaction_type;
                            $friendTransactions[$iterator]['amount'] = $bill->bill_amount;
                            $friendTransactions[$iterator]['description'] = $bill->description;
                            $friendTransactions[$iterator]['note'] = $transaction->note;
                            $friendTransactions[$iterator]['image'] = $transaction->image;
                            $splitOptionsType = $bill->split_option_type;
                            $friendTransactions[$iterator]['splitOptionsType'] = $splitOptionsType;
                            $friendTransactions[$iterator]['billUsers'] = $this->formatResponseBillUsers($transaction->billUsers, $splitOptionsType);
                            $friendTransactions[$iterator]['transactions'] = $this->formatResponseBillTransactions($transaction->billTransactions);
                            $friendTransactions[$iterator]['icon'] = $this->formatResponseBillIcon($bill->billIcon);
                            break;
                        default :
                            break;
                    }
                    $iterator++;
                }
            }

            return $friendTransactions;
        }catch (\Exception $exception){
            throw new ModelNotFoundException($exception->getMessage());
        }
    }

    protected function formatResponseBillIcon($billIcon){
        try{
            $icon = array();
            $icon['type'] = $billIcon['type'];
            $icon['iconName'] = $billIcon['icon_name'];
            $icon['name'] = $billIcon['name'];
            $icon['iconBackgroundColor'] = $billIcon['icon_bg_color'];

            return $icon;

        }catch(\Exception $exception){
            throw new ModelNotFoundException($exception->getMessage());
        }

    }

    protected function formatResponseBillUsers($billUsers, $splitOptionsType){
        try{
            $users = array();
            foreach ($billUsers as $key => $billUser){
                $users[$key]['userId'] = $billUser['user_id'];
                $users[$key]['userOwe'] = $billUser['user_owe'];
                $users[$key]['userPaid'] = $billUser['user_paid'];
                $users[$key]['userIn'] = $billUser['user_in'];
                switch ($splitOptionsType){
                    case 'equally':
                        $users[$key]['userInEqually'] = $billUser['user_in'] == 0 ? false : true;
                        break;
                    case 'exactAmount':
                        $users[$key]['userInExactAmount'] = $billUser['user_in'] == 0 ? "" : (string)$billUser['user_in'];
                        break;
                    case 'percentages':
                        $users[$key]['userInPercentage'] = $billUser['user_in'] == 0 ? "" : (string)$billUser['user_in'];
                        break;
                    case 'shares':
                        $users[$key]['userInShares'] = $billUser['user_in'] == 0 ? "" : (string)$billUser['user_in'];
                        break;
                    case 'adjustment':
                        $users[$key]['userInAdjustment'] = $billUser['user_in'] == 0 ? "" : (string)$billUser['user_in'];
                        break;
                    default:
                        break;
                }
            }
            return $users;

        }catch(\Exception $exception){
            throw new ModelNotFoundException($exception->getMessage());
        }

    }

    protected function formatResponseBillTransactions($billTransactions){
        try{
            $transaction = array();
            foreach ($billTransactions as $key => $billTransaction){
                $transaction[$key]['userOwe'] = $billTransaction['user_owe_id'];
                $transaction[$key]['userPaid'] = $billTransaction['user_paid_id'];
                $transaction[$key]['amount'] = $billTransaction['amount'];
            }
            return $transaction;

        }catch(\Exception $exception){
            throw new ModelNotFoundException($exception->getMessage());
        }

    }

    protected function formatResponseActivityTransaction($transaction){
        try{
            $activityTransaction = array();
            if($transaction != null ){
                $activityTransaction['transactionId'] = $transaction->transactionId;
                $activityTransaction['addedOn'] = $transaction->created_at->format('Y-m-d H:i:sP');
                $activityTransaction['addedByUserId'] = $transaction->added_by_user_id;
                $activityTransaction['date'] = $transaction->added_on->format('Y-m-d H:i:sP');
                $activityTransaction['isSynced'] = true;
                $activityTransaction['note'] = $transaction->note;

                $activityTransaction['image'] = $transaction->image == null ? null : env('APP_URL').env('TRANSACTION_IMAGE_PATH').$transaction->image;

                switch ($transaction->transaction_type){
                    case 'payment':
                        $activityTransaction['type'] = $transaction->transaction_type;
                        $activityTransaction['amount'] = $transaction->payment->first()->payment_amount;
                        $activityTransaction['groupId'] = $transaction->group_id;
                        $activityTransaction['billId'] = $transaction->bill_id;
                        $activityTransaction['icon'] = $transaction->payment->first()->icon;
                        $activityTransaction['userOwe'] = $transaction->payment->first()->user_owe_id;
                        $activityTransaction['userPaid'] = $transaction->payment->first()->user_paid_id;

                        break;
                    case 'bill':
                        $bill = Bill::withTrashed()->where('id', $transaction->bill_id)->first();
                        $activityTransaction['groupId'] = $transaction->group_id;
                        $activityTransaction['billId'] = $transaction->bill_id;
                        $activityTransaction['type'] = $transaction->transaction_type;
                        $activityTransaction['amount'] = $bill->bill_amount;
                        $activityTransaction['description'] = $bill->description;

                        $splitOptionsType = $bill->split_option_type;
                        $activityTransaction['splitOptionsType'] = $splitOptionsType;
                        $activityTransaction['billUsers'] = $this->formatResponseBillUsers($transaction->billUsers, $splitOptionsType);
                        $activityTransaction['transactions'] = $this->formatResponseBillTransactions($transaction->billTransactions);
                        $activityTransaction['icon'] = $this->formatResponseBillIcon($bill->billIcon);
                        break;
                    default :
                        break;
                }
            }
            return $activityTransaction;
        }catch (\Exception $exception){
            throw new ModelNotFoundException($exception->getMessage());
        }
    }

    protected function formatResponseActivities($activities){
        try{
            $userActivities = array();

            foreach ($activities as $key => $activity){

                $transaction = Transaction::withTrashed()->where('transactionId', $activity['transaction_id'])->first();
                $userActivities[$key]['activityId'] = $activity['activityId'];
                $userActivities[$key]['transactionId'] = $activity['transaction_id'];
                $userActivities[$key]['addedByUserId'] = $activity['added_by_user_id'];
                $userActivities[$key]['type'] = $activity['type'];
                $userActivities[$key]['activityType'] = $activity['activity_type'];
                $userActivities[$key]['addedOn'] = $activity['created_at']->format('Y-m-d H:i:sP');
                $userActivities[$key]['groupId'] = $activity['group_id'];
                $userActivities[$key]['isSynced'] = true;
                $userActivities[$key]['addedUser'] = $activity['added_user'];
                $userActivities[$key]['removedUser'] = $activity['removed_user'];
                if($activity['group_id'] &&  $activity['transaction_id'] == null){
                    $group = Group::withTrashed()->where('id', $activity['group_id'])->first();
                    $userActivities[$key]['groupName'] = $group['group_name'];
                    $userActivities[$key]['type'] = 'group';
                    $userActivities[$key]['icon'] = $group['icon'];

                }

                if($activity['transaction_id'] != null){
                    switch ($activity['type']){
                        case 'group':
                            $userActivities[$key]['type'] = $transaction->transaction_type;
                            $userActivities[$key]['billId'] = $transaction->bill_id;
                            $userActivities[$key]['icon'] = $transaction->group->first()->icon;
                            break;
                        case 'payment':
                            $payment = Payment::withTrashed()->where('transaction_id', $transaction->id)->first();
                            $userActivities[$key]['type'] = $transaction->transaction_type;
                            $userActivities[$key]['amount'] = $payment->payment_amount;
                            $userActivities[$key]['billId'] = $transaction->bill_id;
                            $userActivities[$key]['icon'] = $payment->icon;
                            $userActivities[$key]['userOwe'] = $payment->user_owe_id;
                            $userActivities[$key]['userPaid'] = $payment->user_paid_id;
                            $userActivities[$key]['note'] = $transaction->note;
                            $userActivities[$key]['image'] = $transaction->image;
                            $userActivities[$key]['paymentTransaction'] = $this->formatResponseActivityTransaction($transaction);
                            break;
                        case 'bill':
                            $bill = Bill::withTrashed()->where('id', $transaction->bill_id)->first();
                            $userActivities[$key]['billId'] = $transaction->bill_id;
                            $userActivities[$key]['type'] = $transaction->transaction_type;
                            $userActivities[$key]['amount'] = $bill->bill_amount;
                            $userActivities[$key]['description'] = $bill->description;
                            $userActivities[$key]['note'] = $transaction->note;
                            $userActivities[$key]['image'] = $transaction->image;
                            $splitOptionsType = $bill->split_option_type;
                            $userActivities[$key]['splitOptionsType'] = $splitOptionsType;
                            $userActivities[$key]['billUsers'] = $this->formatResponseBillUsers($transaction->billUsers, $splitOptionsType);
                            $userActivities[$key]['transactions'] = $this->formatResponseBillTransactions($transaction->billTransactions);
                            $userActivities[$key]['icon'] = $this->formatResponseBillIcon($bill->billIcon);
                            $userActivities[$key]['billTransaction'] = $this->formatResponseActivityTransaction($transaction);
                            break;
                        default :
                            break;
                    }
                }
            }
            return $userActivities;
        }catch (\Exception $exception){
            throw new ModelNotFoundException($exception->getMessage());
        }
    }

    protected function formatResponseGroup($groups){
        try{
            $userGroups = array();
            foreach ($groups as $key => $group){
                $userGroups[$key]['groupId'] = $group['id'];
                $userGroups[$key]['groupName'] = $group['group_name'];
                $userGroups[$key]['icon'] = $group['icon'];
                $userGroups[$key]['type'] = 'group';
                $userGroups[$key]['date'] = $group['created_at']->format('Y-m-d H:i:sP');
                $userGroups[$key]['groupUsers'] = $this->formatResponseGroupUsers($group->groupUsers);
                $userGroups[$key]['balances'] = $this->formatResponseGroupBalances($group->groupBalances);
                $userGroups[$key]['transactions'] = $this->formatResponseGroupTransaction($group->transactions);
                $userGroups[$key]['isSynced'] = true;

            }
            return $userGroups;
        }catch(\Exception $exception){
            throw new ModelNotFoundException($exception->getMessage());
        }
    }


    protected function formatResponseGroupUsers($groupUsers){
        try{
            $users = array();
            foreach ($groupUsers as $key => $groupUser){
                $users[$key]['userId'] = $groupUser['user_id'];
                $users[$key]['userOwe'] = $groupUser['user_owe'];
                $users[$key]['userPaid'] = $groupUser['user_paid'];
            }
            return $users;

        }catch(\Exception $exception){
            throw new ModelNotFoundException($exception->getMessage());
        }

    }

    protected function formatResponseGroupBalances($groupBalances){
        try{
            $balances = array();
            foreach ($groupBalances as $key => $groupBalance){
                $balances[$key]['id'] = $groupBalance['id'];
                $balances[$key]['userOwe'] = $groupBalance['user_owe_id'];
                $balances[$key]['userPaid'] = $groupBalance['user_paid_id'];
                $balances[$key]['amount'] = $groupBalance['amount'];
            }
            return $balances;

        }catch(\Exception $exception){
            throw new ModelNotFoundException($exception->getMessage());
        }

    }

}
