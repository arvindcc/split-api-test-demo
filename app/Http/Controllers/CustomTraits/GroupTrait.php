<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 20/1/19
 * Time: 8:19 PM
 */

namespace App\Http\Controllers\CustomTraits;

use App\Group;
use App\GroupBalance;
use App\GroupUser;
use App\Log as CustomLog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait GroupTrait
{
    use TransactionTrait;

    protected function addGroup($input){
        $group = null;
        try{
            $group = new Group();
            $group['user_id'] = Auth::user()->id;
            $group['group_name'] = $input['groupName'];
            $group['icon'] = $input['groupIcon'];
            $group->save();
        }catch (\Exception $exception){
            $status = 500;
            $message = $exception->getMessage();
            $data = [
                'action' => 'Create Group in GroupTrait',
                'parameters' => $input,
                'message' => $message,
                'status' => 500,
            ];
            Log::critical(json_encode($data, $status));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Create Group in GroupTrait';
            $log['request_parameter'] = $input;
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
        }
        return $group;
    }
    protected function createGroupUser($input, $groupId){
        $groupUser = null;
        try{
            $groupUser = new GroupUser();
            $groupUser['user_id'] = Auth::user()->id;
            $groupUser['group_id'] = $groupId;
            $groupUser['user_owe'] = $input['userOwe'] == null ? 0 : $input['user_owe'];
            $groupUser['user_paid'] = $input['userPaid'] == null ? 0: $input['user_paid'];
            $groupUser->save();
        }catch (\Exception $exception){
            $status = 500;
            $message = $exception->getMessage();
            $data = [
                'action' => 'Create GroupUser in GroupTrait',
                'parameters' => $input,
                'message' => $message,
                'status' => 500,
            ];
            Log::critical(json_encode($data, $status));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Create GroupUser in GroupTrait';
            $log['request_parameter'] = $groupId;
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
        }
        return $groupUser;
    }
    protected function updateGroup($group){
        try{
            //$group = Group::where('id', $group['groupId'])->get();
            $updateStatus = Group::where('id', $group['groupId'])->update([
                'group_name' => $group['groupName'],
                'icon' => $group['icon']
            ]);
            if ($updateStatus){
                return true;
            }else{
                return false;
            }

        }catch (\Exception $exception){
            $status = 500;
            $message = $exception->getMessage();
            $data = [
                'action' => 'Update Group in GroupTrait',
                'parameters' => $group,
                'message' => $message,
                'status' => 500,
            ];
            Log::critical(json_encode($data, $status));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Update Group in GroupTrait';
            $log['request_parameter'] = $group;
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
        }
    }

    protected function syncGroup($groups){
        try{
            foreach ($groups as $group){
                $this->syncGroupUser($group['groupId'], $group['groupUsers']);
                $this->syncGroupBalance($group['groupId'], $group['balances']);
            }
            return true;
        }catch (\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('syncGroup: '.$exception->getMessage());
        }
    }

    protected function syncGroupUser($groupId, $groupUsers){
        try{
            foreach ($groupUsers as $groupUser){
                GroupUser::where('group_id', $groupId)
                    ->where('user_id',$groupUser['userId'])
                    ->update([
                        'user_owe' => $groupUser['userOwe'] == null ? 0 : $groupUser['userOwe'],
                        'user_paid' => $groupUser['userPaid'] == null ? 0: $groupUser['userPaid'],
                    ]);
            }
        }catch (\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('syncGroupUser: '.$exception->getMessage());
        }
    }

    protected function syncGroupBalance($groupId, $groupBalances){
        try{
            $serverGroupBalances = GroupBalance::where('group_id', $groupId)->get();
            if(count($serverGroupBalances) != count($groupBalances)){
                foreach ($serverGroupBalances as  $serverGroupBalance){
                    foreach ($groupBalances as $groupBalance){
                        if($groupBalance['id'] == $serverGroupBalance['id']){
                                $this->updateGroupBalance($groupId, $groupBalance['userOwe'], $groupBalance['userPaid'], $groupBalance['amount']);
                        }else{
                            $serverGroupBalance->delete();
                        }
                    }

                }
            }else{
                foreach ($groupBalances as $groupBalance){
                    $previousBalance = GroupBalance::where('group_id', $groupId)
                        ->where('user_owe_id', $groupBalance['userOwe'])
                        ->where('user_paid_id', $groupBalance['userPaid'])
                        ->first();

                    $previousReverseBalance = GroupBalance::where('group_id', $groupId)
                        ->where('user_owe_id', $groupBalance['userPaid'])
                        ->where('user_paid_id', $groupBalance['userOwe'])
                        ->first();
                    if($previousReverseBalance){
                        $previousReverseBalance->update([
                            "amount" => $groupBalance['amount'],
                        ]);
                    }else{
                        $previousBalance->update([
                            "amount" => $groupBalance['amount'],
                        ]);
                    }
                }
            }

        }catch (\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('syncGroupBalance: '.$exception->getMessage());
        }
    }

    protected function createGroupBalance($groupId, $userOweId, $userPaidId, $amount){
        try{
            $syncGroupBalance = new GroupBalance();
            $syncGroupBalance['user_owe_id'] = $userOweId;
            $syncGroupBalance['user_paid_id'] = $userPaidId;
            $syncGroupBalance['amount'] = $amount;
            $syncGroupBalance['group_id'] = $groupId;
            $syncGroupBalance->save();
        }catch (\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('createGroupBalance: '.$exception->getMessage());
        }
    }


    protected function updateGroupBalance($groupId, $userOweId, $userPaidId, $amount){
        try{
            GroupBalance::where('group_id', $groupId)
                ->whereIn('user_owe_id', [$userOweId, $userPaidId])
                ->whereIn('user_paid_id', [$userOweId, $userPaidId])
                ->update([
                    'user_owe_id' => $userOweId,
                    'user_paid_id' => $userPaidId,
                    'amount' => $amount,
                ]);
        }catch (\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('updateGroupBalance: '.$exception->getMessage());
        }
    }

    protected function deleteGroupBalance($groupId, $userOweId, $userPaidId, $amount){
        try{
            GroupBalance::where('group_id', $groupId)
                ->whereIn('user_owe_id', [$userOweId, $userPaidId])
                ->whereIn('user_paid_id', [$userOweId, $userPaidId])
                ->whereIn('amount', $amount)
                ->delete();
        }catch (\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('updateGroupBalance: '.$exception->getMessage());
        }
    }

    protected function calcGroupBalance($oweId, $paidId, $amount, $groupId, $type){
        try{
            $previousBalance = null;
            $previousReverseBalance = null;

            switch ($type){
                case 'add':
                    $previousBalance = GroupBalance::where('group_id', $groupId)
                        ->where('user_owe_id', $oweId)
                        ->where('user_paid_id', $paidId)
                        ->first();

                    $previousReverseBalance = GroupBalance::where('group_id', $groupId)
                        ->where('user_owe_id', $paidId)
                        ->where('user_paid_id', $oweId)
                        ->first();

                    if ($previousReverseBalance){
                        $previousReverseAmount = $previousReverseBalance->amount;
                        if($previousReverseAmount > $amount){
                            $amount = $previousReverseAmount - $amount;
                            $this->updateGroupBalance($groupId, $paidId, $oweId, $amount);
                        }elseif ($previousReverseAmount < $amount){
                            $amount =  $amount - $previousReverseAmount;
                            $this->updateGroupBalance($groupId, $oweId, $paidId, $amount);
                        }elseif ($previousReverseAmount = $amount){
                            $previousReverseBalance->delete();
                        }
                    }
                    if($previousBalance){
                        $previousAmount = $previousBalance->amount;
                        $amount = $previousAmount + $amount;
                        $this->updateGroupBalance($groupId, $oweId, $paidId, $amount);
                    }
                    if(!$previousBalance && !$previousReverseBalance){
                        $this->createGroupBalance($groupId, $oweId, $paidId, $amount);
                    }

                    break;
                case 'delete':
                    $previousBalance = GroupBalance::where('group_id', $groupId)
                        ->where('user_owe_id', $paidId)
                        ->where('user_paid_id', $oweId)
                        ->first();

                    $previousReverseBalance = GroupBalance::where('group_id', $groupId)
                        ->where('user_owe_id', $oweId)
                        ->where('user_paid_id', $paidId)
                        ->first();

                    if ($previousReverseBalance){
                        $previousReverseAmount = $previousReverseBalance->amount;
                        if($previousReverseAmount > $amount){
                            $amount = $previousReverseAmount - $amount;
                            $this->updateGroupBalance($groupId, $oweId, $paidId, $amount);
                        }elseif ($previousReverseAmount < $amount){
                            $amount =  $amount - $previousReverseAmount;
                            $this->updateGroupBalance($groupId, $paidId, $oweId, $amount);
                        }elseif ($previousReverseAmount = $amount){
                            $previousReverseBalance->delete();
                        }
                    }
                    if($previousBalance){
                        $previousAmount = $previousBalance->amount;
                        $amount = $previousAmount - $amount;
                        $this->updateGroupBalance($groupId, $paidId, $oweId, $amount);
                    }
                    if(!$previousBalance && !$previousReverseBalance){
                        $this->createGroupBalance($groupId, $paidId, $oweId, $amount);
                    }

                    break;
                default:
                    break;

            }
        }catch (\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('calcGroupBalance: '.$exception->getMessage());
        }
    }

}
