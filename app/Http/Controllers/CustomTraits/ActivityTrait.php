<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17/2/19
 * Time: 2:42 PM
 */

namespace App\Http\Controllers\CustomTraits;


use App\Activity;
use App\Bill;
use App\BillIcon;
use App\BillTransaction;
use App\BillUser;
use App\Group;
use App\GroupBalance;
use App\GroupUser;
use App\Transaction;
use App\UserHasFriend;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

trait ActivityTrait
{
    use TransactionTrait;
    use UserTrait;

    protected function createActivity($activity, $transactionId, $userId, $addedUser, $removedUser){
        try{
            $syncActivity = new Activity();
            $syncActivity['activityId'] = $activity['activityId'];
            $syncActivity['activity_type'] = $activity['activityType'];
            $syncActivity['type'] = $activity['type'];
            $syncActivity['transaction_id'] = $transactionId;
            $syncActivity['added_by_user_id'] = $activity['addedByUserId'];
            $syncActivity['user_id'] = $userId;
            $syncActivity['group_id'] = $activity['groupId'];
            $syncActivity['created_at'] = Carbon::createFromTimeString($activity['addedOn']);

            $syncActivity['added_user'] = $addedUser;
            $syncActivity['removed_user'] = $removedUser;

            if($syncActivity->save()){
                $this->incrementTransactionCount($userId);
            }
        }catch(\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('createActivity: '.$exception->getMessage());
        }
    }


    protected function syncActivity($activities){
        try{
            foreach ($activities as $activity) {
                if(!$activity['isSynced']){
                    $activityType = $activity['activityType'];
                    switch ($activityType) {
                        case 'add':
                            $this->addSyncActivity($activity);
                            break;

                        case 'delete':
                            $this->deleteSyncActivity($activity);
                            break;

                        case 'edit':
                            $this->editSyncActivity($activity);
                            break;
                        case 'remove':
                            $this->removeSyncActivity($activity);
                            break;

                        case '':
                            break;

                        default:
                            // code...
                            break;
                    }
                }
            }
            return true;
        }catch (\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('syncActivity: '.$exception->getMessage());
        }
    }

    protected function addSyncActivity($activity){
        try{
            switch ($activity['type']){
                case 'bill':
                    $transaction = $activity['transaction'];
                    $billIcon = $this->syncBillIcon($transaction['icon']);
                    if($billIcon != null){
                        $bill = $this->syncBill($transaction['description'], $transaction['splitOptionsType'], $billIcon['id'], $transaction['amount']);
                        $billUsers = $transaction['billUsers'];
                        foreach ($billUsers as $billUser1){
                            foreach ($billUsers as $billUser2){
                                if($billUser1['userId'] != $billUser2['userId']){
                                    $userHasFriend = UserHasFriend::whereIn('user_id', [$billUser1['userId'], $billUser2['userId']])
                                        ->whereIn('friend_id', [$billUser1['userId'], $billUser2['userId']])
                                        ->first();
                                    if(!$userHasFriend){
                                        $userHasFriend = new UserHasFriend();
                                        $userHasFriend['user_id'] = $billUser1['userId'];
                                        $userHasFriend['friend_id'] = $billUser2['userId'];
                                        $userHasFriend['amount'] = 0;
                                        $userHasFriend->save();

                                    }
                                }
                            }
                        }
                        foreach ($billUsers as $billUser){
                            if($billUser['userId'] != Auth::user()->id){
                                $userHasFriend = UserHasFriend::whereIn('friend_id', [ $billUser['userId'], Auth::user()->id])
                                ->whereIn('user_id', [ $billUser['userId'], Auth::user()->id])
                                ->first();
                            if($userHasFriend){
                                $this->syncTransaction($transaction, $userHasFriend['id'], $bill->id);
                                }
                                $this->createActivity($activity, $activity['transactionId'], $billUser['userId'], null, null);
                            }
                        }

                        $billTransactions = $transaction['transactions'];
                        foreach ($billTransactions as $billTransaction) {

                            if($billTransaction['userPaid'] != Auth::user()->id && $billTransaction['userOwe'] != Auth::user()->id){
                                $userHasFriend = UserHasFriend::whereIn('user_id', [$billTransaction['userOwe'], $billTransaction['userPaid']])
                                    ->whereIn('friend_id', [$billTransaction['userOwe'], $billTransaction['userPaid']])
                                    ->first();

                                if($userHasFriend['user_id'] == $billTransaction['userOwe']){
                                    $amount = $userHasFriend['amount'] - $billTransaction['amount'];
                                    $userHasFriend->update([
                                        'amount' => $amount
                                    ]);
                                }else{
                                    $amount = $userHasFriend['amount'] + $billTransaction['amount'];
                                    $userHasFriend->update([
                                        'amount' => $amount
                                    ]);
                                }
                                $this->syncTransaction($transaction, $userHasFriend['id'], $bill->id);
                            }
                        }
                        foreach ($billUsers as $billUser){
                            if($activity['groupId']){
                                $groupUser = GroupUser::where('group_id', $activity['groupId'])
                                                        ->where('user_id', $billUser['userId'])
                                                        ->first();
                                if(!$groupUser){
                                    $syncGroupUser = new GroupUser();
                                    $syncGroupUser['user_id'] =  $billUser['userId'];
                                    $syncGroupUser['group_id'] =  $activity['groupId'];
                                    $syncGroupUser['user_owe'] = 0;
                                    $syncGroupUser['user_paid'] = 0;
                                    $syncGroupUser->save();
                                }

                            }
                        }
                        $this->createActivity($activity, $activity['transactionId'], Auth::user()->id, null, null);
                    }

                    break;
                case 'payment':
                    $transaction = $activity['transaction'];

                    $userHasFriend = UserHasFriend::whereIn('user_id', [$activity['userOwe'], $activity['userPaid']])
                        ->whereIn('friend_id', [$activity['userOwe'], $activity['userPaid']])->first();
                    if(!$userHasFriend){
                        $userHasFriend = new UserHasFriend();
                        $userHasFriend['user_id'] = $activity['userOwe'];
                        $userHasFriend['friend_id'] = $activity['userPaid'];
                        $userHasFriend['amount'] = $transaction['amount'];
                        $userHasFriend->save();
                    }
                    $this->syncTransaction($transaction, $userHasFriend['id'], null);
                    $this->createActivity($activity, $activity['transactionId'], $activity['userOwe'], null, null);
                    $this->createActivity($activity, $activity['transactionId'], $activity['userPaid'], null, null);
                    if($activity['userOwe'] != Auth::user()->id && $activity['userPaid'] != Auth::user()->id){
                        $this->createActivity($activity, $activity['transactionId'], Auth::user()->id, null, null);
                    }
                    break;

                case 'group':
                    $addedUser = $activity['addedUser'];
                    $removedUser = null;
                    $groupUsers = GroupUser::where('group_id', $activity['groupId'])->get();
                    foreach ($groupUsers as $groupUser){
                        if($activity['addedUser'] != Auth::user()->id || $groupUser['user_id'] != Auth::user()->id){
                            $userHasFriend = UserHasFriend::whereIn('user_id', [$activity['addedUser'], $groupUser['user_id']])
                                ->whereIn('friend_id', [$activity['addedUser'], $groupUser['user_id']])
                                ->first();
                            if(!$userHasFriend){
                                $userHasFriend = new UserHasFriend();
                                $userHasFriend['user_id'] = $activity['addedUser'];
                                $userHasFriend['friend_id'] = $groupUser['user_id'];
                                $userHasFriend['amount'] = 0;
                                $userHasFriend->save();
                            }
                        }

                    }
                    $syncGroupUser = new GroupUser();
                    $syncGroupUser['user_id'] =  $activity['addedUser'];
                    $syncGroupUser['group_id'] =  $activity['groupId'];
                    $syncGroupUser['user_owe'] = 0;
                    $syncGroupUser['user_paid'] = 0;
                    $syncGroupUser->save();
                    if(Auth::user()->id != $activity['addedUser']){
                        $this->createActivity($activity, null, Auth::user()->id, $addedUser, $removedUser);
                    }
                    $this->createActivity($activity, null, $activity['addedUser'], $addedUser, $removedUser);
                    break;

                default:
                    break;
            }

        }catch(\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('addSyncActivity: '.$exception->getMessage());
        }

    }

    protected function editSyncActivity($activity){
        try{
            switch ($activity['type']){
                case 'payment':
                    $oldTransactions = Transaction::where('transactionId', $activity['transactionId'])->first();
                    $this->updateSyncTransactionTypePayment($activity['transaction']);
                    $this->createActivity($activity, $activity['transactionId'], $oldTransactions->friend->friend_id, null, null);

                    break;
                case 'bill':
                    $oldTransactions = Transaction::where('transactionId', $activity['transactionId'])->get();
                    $transaction = $activity['transaction'];

                    $transactionToEdit = Transaction::where('transactionId',  $activity['transactionId'])->first();
                    $billIdToEdit = $transactionToEdit->bill_id;
                    $billToEdit = Bill::where('id', $billIdToEdit)->first();
                    $oldBillTransactions = $transactionToEdit->billTransactions;
                    foreach ($oldBillTransactions as $billTransaction){
                        if($billTransaction['user_paid_id'] != Auth::user()->id && $billTransaction['user_owe_id'] != Auth::user()->id){
                            $userHasFriend = UserHasFriend::whereIn('user_id', [$billTransaction['user_owe_id'], $billTransaction['user_paid_id']])
                                ->whereIn('friend_id', [$billTransaction['user_owe_id'], $billTransaction['user_paid_id']])
                                ->first();
                            if($userHasFriend['user_id'] == $billTransaction['user_owe_id']){
                                $amount = $userHasFriend['amount'] + $billTransaction['amount'];
                                $userHasFriend->update([
                                    'amount' => $amount
                                ]);
                            }else{
                                $amount = $userHasFriend['amount'] - $billTransaction['amount'];
                                $userHasFriend->update([
                                    'amount' => $amount
                                ]);
                            }
                        }
                        if($activity['groupId']!=null){
                            $this->calcGroupBalance($billTransaction['user_owe_id'], $billTransaction['user_paid_id'], $billTransaction['amount'], $activity['groupId'], 'delete');
                        }
                    }

                    foreach ($oldTransactions as $oldTransaction){
                        $bill = $oldTransaction->bill;
                        $bill->update([
                            'description' => $activity['transaction']['description'],
                            'split_option_type' => $activity['transaction']['splitOptionsType'],
                            'bill_amount' => $activity['transaction']['amount']
                        ]);
                        $this->updateSyncTransactionTypeBill($activity['transaction'], $oldTransaction, $bill);

                        $billUsers = $transaction['billUsers'];
                        foreach ($billUsers as $billUser){
                            if($billUser['userId'] != Auth::user()->id){
                                $this->createActivity($activity, $activity['transactionId'], $billUser['userId'], null, null);
                            }
                        }
                        $this->createActivity($activity, $activity['transactionId'], Auth::user()->id, null, null);
                    }


                    $billTransactions = $transaction['transactions'];
                    foreach ($billTransactions as $billTransaction){
                        if($billTransaction['userPaid'] != Auth::user()->id && $billTransaction['userOwe'] != Auth::user()->id){
                            $userHasFriend = UserHasFriend::whereIn('user_id', [$billTransaction['userOwe'], $billTransaction['userPaid']])
                                ->whereIn('friend_id', [$billTransaction['userOwe'], $billTransaction['userPaid']])
                                ->first();
                            if($userHasFriend['user_id'] == $billTransaction['userOwe']){
                                $amount = $userHasFriend['amount'] - $billTransaction['amount'];
                                $userHasFriend->update([
                                    'amount' => $amount
                                ]);
                            }else{
                                $amount = $userHasFriend['amount'] + $billTransaction['amount'];
                                $userHasFriend->update([
                                    'amount' => $amount
                                ]);
                            }
                        }
                        if($activity['groupId']!=null){
                            $this->calcGroupBalance($billTransaction['userOwe'], $billTransaction['userPaid'], $billTransaction['amount'], $activity['groupId'], 'add');
                        }
                    }
                case 'group':

                    break;
            }

            $this->createActivity($activity, $activity['transactionId'], Auth::user()->id, null, null);

        }catch (\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('editSyncActivity: '.$exception->getMessage());
        }

    }

    protected function deleteSyncActivity($activity){
        try{
            switch ($activity['type']){
                case 'bill':
                    $activityTransaction = $activity['transaction'];
                    $billUsers = $activityTransaction['billUsers'];
                    foreach ($billUsers as $billUser){
                        if($billUser['userId'] != Auth::user()->id){
                            $this->createActivity($activity, $activity['transactionId'], $billUser['userId'], null, null);
                        }
                    }

                    $billTransactions = $activityTransaction['transactions'];
                    foreach ($billTransactions as $billTransaction){
                        if($billTransaction['userPaid'] != Auth::user()->id && $billTransaction['userOwe'] != Auth::user()->id){
                            $userHasFriend = UserHasFriend::whereIn('user_id', [$billTransaction['userOwe'], $billTransaction['userPaid']])
                                ->whereIn('friend_id', [$billTransaction['userOwe'], $billTransaction['userPaid']])
                                ->first();
                            if($userHasFriend['user_id'] == $billTransaction['userOwe']){
                                $amount = $userHasFriend['amount'] + $billTransaction['amount'];
                                $userHasFriend->update([
                                    'amount' => $amount
                                ]);
                            }else{
                                $amount = $userHasFriend['amount'] - $billTransaction['amount'];
                                $userHasFriend->update([
                                    'amount' => $amount
                                ]);
                            }
                        }
                        if($activity['groupId']!=null){
                            $this->calcGroupBalance($billTransaction['userOwe'], $billTransaction['userPaid'], $billTransaction['amount'], $activity['groupId'], 'delete');
                        }
                    }
                    $transactionToDelete = Transaction::where('transactionId',  $activity['transactionId'])->first();
                    $billIdToDelete = $transactionToDelete->bill_id;
                    $billToDelete = Bill::where('id', $billIdToDelete)->first();
                    $billToDelete->delete();

                    Transaction::where('transactionId',  $activity['transactionId'])->delete();
                    BillUser::where('bill_id', $billIdToDelete)->delete();
                    BillTransaction::where('bill_id', $billIdToDelete)->delete();

                    $this->createActivity($activity, $activity['transactionId'], Auth::user()->id, null, null);
                    break;
                case 'payment':
                    $transaction = $activity['transaction'];
                    if($transaction['groupId']!=null){
                        $this->calcGroupBalance($transaction['userOwe'], $transaction['userPaid'], $transaction['amount'], $transaction['groupId'], 'delete');
                    }
                    $this->createActivity($activity, $activity['transactionId'], $transaction['userOwe'], null, null);
                    $this->createActivity($activity, $activity['transactionId'], $transaction['userPaid'], null, null);
                    if($transaction['userOwe'] != Auth::user()->id && $transaction['userPaid'] != Auth::user()->id){
                        $this->createActivity($activity, $activity['transactionId'], Auth::user()->id, null, null);
                    }
                    $transactionToDelete = Transaction::where('transactionId',  $activity['transactionId'])->first();
                    $paymentToDelete = Transaction::where('transactionId', $activity['transactionId'])->first()->payment->first();
                    $paymentToDelete->delete();
                    $transactionToDelete->delete();
                    break;
                case 'group':
                    $group = Group::where('id', $activity['groupId'])->first();
                    $groupUsers = $group->groupUsers;
                    $group->delete();
                    GroupBalance::where('group_id', $activity['groupId'])->delete();
                    foreach ($groupUsers as $groupUser){
                       if($groupUser['user_id'] != Auth::user()->id){
                           $this->createActivity($activity, null, $groupUser['user_id'], null, null);
                       }
                       $groupUser->delete();
                    }

                    $this->createActivity($activity, null, Auth::user()->id, null, null);

            }

        }catch(\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('deleteSyncActivity: '.$exception->getMessage());
        }

    }

    protected function removeSyncActivity($activity){
        try{
            $addedUser = null;
            $removedUser = $activity['removedUser'];
            if($activity['type'] == 'group'){

                GroupUser::where('group_id', $activity['groupId'])
                            ->where('user_id', $activity['removedUser'])
                            ->delete();
                GroupBalance::where('group_id', $activity['groupId'])
                    ->where('user_owe_id', $activity['removedUser'])
                    ->orWhere('user_paid_id', $activity['removedUser'])
                    ->delete();
                $userHasFriend = UserHasFriend::where('user_id', $activity['addedByUserId'])
                    ->where('friend_id', $activity['removedUser'])
                    ->first();

                Transaction::where('group_id', $activity['groupId'])
                            ->where('user_friends_id', $userHasFriend['id'])
                            ->delete();

                $this->createActivity($activity, null, Auth::user()->id, $addedUser, $removedUser);
                $this->createActivity($activity, null, $activity['removedUser'], $addedUser, $removedUser);

            }
        }catch(\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('removeSyncActivity'.$exception->getMessage());
        }

    }


}
