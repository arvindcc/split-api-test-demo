<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 20/1/19
 * Time: 9:04 PM
 */

namespace App\Http\Controllers\Group;

use App\Bill;
use App\Group;
use App\GroupUser;
use App\Http\Controllers\CustomTraits\GroupTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Routing\Controller as BaseController;
use App\Log as CustomLog;

class GroupController extends BaseController
{
    use GroupTrait;

    public function __construct(){
        $this->middleware('jwt.auth');
        if(!Auth::guest()){
            $this->user = Auth::user();
        }
    }

    public function createGroup(Request $request){
        try{
            $addGroupResponse = array();
            $groupInput = $request['group'];
            $groupUserInput = $request['groupUsers'];
            $group = $this->addGroup($groupInput);
            if($group != null){
              $addGroupResponse['groupId'] = $group['id'];
              $addGroupResponse['groupName'] = $group['group_name'];
              $addGroupResponse['icon'] = $group['icon'];
              $addGroupResponse['date'] = $group['created_at'];
              /*
              $created_at = $group['created_at'];
              $date = Carbon::parse($created_at);
              $addGroupResponse['date'] = $date->format('d D M Y');
              */

              $groupUser = $this->createGroupUser($groupUserInput, $group->id);
                if($groupUser != null){
                    $message = 'Group Created Successfully';
                    $addGroupResponse['groupUsers'] = array();
                    $addGroupResponse['groupUsers'][0]['userId'] = $groupUser['user_id'];
                    $addGroupResponse['groupUsers'][0]['userOwe'] = $groupUser['user_owe'];
                    $addGroupResponse['groupUsers'][0]['userPaid'] = $groupUser['user_paid'];
                    $addGroupResponse['type'] = 'group';
                    $addGroupResponse['balances'] = array();
                    $addGroupResponse['transactions'] = array();

                }else{
                    $message = 'Unable to create group users';
                }
            }else{
                $message = 'Unable to create group';
            }
            $data = [
                'group' => $addGroupResponse,
                'message' => $message,
                'status' => 200,
            ];
        }catch (\Exception $exception){
            $status = 500;
            $message = $exception->getMessage();
            $data = [
                'action' => 'Create Group',
                'parameters' => $request->all(),
                'message' => $message,
                'status' => 500,
            ];
            Log::critical(json_encode($data, $status));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Create Group';
            $log['request_parameter'] = json_encode($request->all());
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
        }
        return response()->json($data, 200);
    }

    public function updateGroupInfo(Request $request){
        try{
            $this->validate($request,[
                'groupId' => 'required',
                'groupName' => 'required',
                'icon' => 'required',
            ]);
            $groupInfo = $request->all();
            if($this->updateGroup($groupInfo)){
                $message = 'Successfully update Group Info';
                $status = 200;
            }else{
                $message = 'Unable to update group';
                $status = 500;

            }
            $data = [
                'group' => $groupInfo,
                'message' => $message,
                'status' => $status,
            ];
        }catch (ValidationException $validationException){
            $data = [
                'errorMessage' => $validationException->errors(),
                'status' => $validationException->status,
            ];
            Log::critical(json_encode($data, $validationException->status));
            return response()->json($data, 200);
        }
        catch (\Exception $exception){
            $status = 500;
            $message = $exception->getMessage();
            $data = [
                'action' => 'Update Group',
                'parameters' => $request->all(),
                'message' => $message,
                'status' => 500,
            ];
            Log::critical(json_encode($data, $status));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Update Group';
            $log['request_parameter'] = $request->all();
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
        }
        return response()->json($data, 200);
    }

    public function addDeleteGroup(Request $request){
        $message = '';
        $status = '';
        $group = array();
        try{
            $this->validate($request,[
                'groupId' => 'required',
            ]);

            $groupId = $request['groupId'];

            $group = Group::withTrashed()->where('id', $groupId)->first();
            $group->restore();
            $message = "Group Restore Successfully";
            $status = 200;

        }catch (\Exception $exception){
            $status = 500;
            $message = $exception->getMessage();
            $data = [
                'action' => 'Add Delete Group',
                'message' => $message,
                'status' => 500,
            ];
            Log::critical(json_encode($data, $status));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Start Sync';
            $log['request_parameter'] = json_encode($request->all());
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
        }
        $response = [
            'group' => $this->formatResponseGroup($group),
            'message' => $message,
            'status' => $status
        ];
        return response()->json($response,200);
    }

    protected function formatResponseGroup($group){
        try{
            $userGroups = array();
            $userGroups['groupId'] = $group['id'];
            $userGroups['groupName'] = $group['group_name'];
            $userGroups['icon'] = $group['icon'];
            $userGroups['type'] = 'group';
            $userGroups['date'] = $group['created_at']->format('Y-m-d H:i:sP');
            GroupUser::withTrashed()->where('group_id', $group['id'])->restore();
            $groupUsers = GroupUser::withTrashed()->where('group_id', $group['id'])->get();
            $userGroups['groupUsers'] = $this->formatResponseGroupUsers($group->groupUsers);
            $userGroups['balances'] = $this->formatResponseGroupBalances($group->groupBalances);
            $userGroups['transactions'] = $this->formatResponseGroupTransaction($group->transactions);

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
                            $friendTransactions[$iterator]['image'] = $transaction->image == null ? null : env('APP_URL').env('TRANSACTION_IMAGE_PATH').$transaction->image;
                            break;
                        case 'bill':
                            $bill = Bill::withTrashed()->where('id', $transaction->bill_id)->first();
                            $friendTransactions[$iterator]['groupId'] = $transaction->group_id;
                            $friendTransactions[$iterator]['billId'] = $transaction->bill_id;
                            $friendTransactions[$iterator]['type'] = $transaction->transaction_type;
                            $friendTransactions[$iterator]['amount'] = $bill->bill_amount;
                            $friendTransactions[$iterator]['description'] = $bill->description;
                            $friendTransactions[$iterator]['note'] = $transaction->note;
                            $friendTransactions[$iterator]['image'] = $transaction->image == null ? null : env('APP_URL').env('TRANSACTION_IMAGE_PATH').$transaction->image;
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
}
