<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 20/1/19
 * Time: 9:04 PM
 */

namespace App\Http\Controllers\Group;

use App\Http\Controllers\CustomTraits\GroupTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
}
