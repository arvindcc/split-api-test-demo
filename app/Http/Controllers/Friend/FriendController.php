<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 1/1/19
 * Time: 1:50 PM
 */

namespace App\Http\Controllers\Friend;

use App\Http\Controllers\CustomTraits\UserTrait;
use App\User;
use App\UserHasFriend;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use App\Log as CustomLog;
use Laravel\Lumen\Routing\Controller as BaseController;


class FriendController extends  BaseController
{
    use UserTrait;

    public function __construct(){
        $this->middleware('jwt.auth');
        if(!Auth::guest()){
            $this->user = Auth::user();
        }
    }

    public function findFriends(Request $request){
        try{
            /*$this->validate($request,[
                'contacts' => 'required',
            ]);*/
            $contacts = $request['contacts'];
            $updatedContacts = collect();
            $users = User::all();
            $users = collect($users);
            $addFriendContact = array();
            $inviteFriendContact = array();
            $iterartor = 0;
            foreach ($contacts as  $contact){
                foreach ($contact['mobile'] as  $mobileNo){
                    $friend = array();
                    $user = $users->where('mobile_no', $mobileNo)->first();
                    $isFriend = UserHasFriend::whereIn('user_id', [Auth::user()->id, $user['id']])
                                                ->whereIn('friend_id', [Auth::user()->id, $user['id']])
                                                ->first();
                    if(!$isFriend){
                        if($user != null){
                            $friend['firstName'] = $user['first_name'];
                            $friend['lastName'] = $user['last_name'];
                            $friend['mobile'] = $mobileNo;
                            $avatar = $user['avatar'];
                            $avatarPath = $avatar == null ? env('DEFAULT_AVATAR_PATH') : env('AVATAR_PATH').$avatar;
                            $friend['avatar'] = env('APP_URL').$avatarPath;
                            $friend['type'] = 'addFriend';
                            $addFriendContact[$iterartor] = $friend;
                            $iterartor++;

                        }else{
                            $friend['firstName'] = $contact['firstName'];
                            $friend['lastName'] = $contact['lastName'];
                            $friend['mobile'] = $mobileNo;
                            $friend['avatar'] = env('APP_URL').env('DEFAULT_AVATAR_PATH');
                            $friend['type'] = 'inviteFriend';
                            $inviteFriendContact[$iterartor] = $friend;
                            $iterartor++;
                        }
                    }

                }
            }
            $addFriendCollection = collect($addFriendContact)->sortBy('firstName');
            $inviteFriendCollection = collect($inviteFriendContact)->sortBy('firstName');
            $updatedContacts = $updatedContacts->merge($addFriendCollection)->merge($inviteFriendCollection);
            $data = [
                'friends' => $updatedContacts->all(),
                'status' => 200,
            ];
        }catch (ValidationException $validationException){
            $data = [
                'errorMessage' => $validationException->errors(),
                'status' => $validationException->status,
            ];
            Log::critical(json_encode($data, $validationException->status));
            return response()->json($data, 200);
        }catch (\Exception $exception){
            $status = 500;
            $message = $exception->getMessage();
            $data = [
                'action' => 'findFriends',
                'parameters' => $request->all(),
                'message' => $message,
                'status' => 500,
            ];
            Log::critical(json_encode($data, $status));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Find Friends';
            $log['request_parameter'] = $request->all();
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
        }
        return response()->json($data, 200);
    }

    public function addFriend(Request $request){
        try{
            $this->validate($request,[
                'mobile' => 'required',
            ]);
            $user = User::where('mobile_no',$request['mobile'])->first();
            if($user != null){
                $userHasFriend = new UserHasFriend();
                $userHasFriend['user_id'] = Auth::user()->id;
                $userHasFriend['friend_id'] = $user['id'];
                $userHasFriend['amount'] = 0;
                $userHasFriend->save();
                $userData = $this->getUserData($user);
                $userData['transactions'] = array();
                $userData['amount'] = 0;
                $message = 'Friend added successfully';
                $status = 200;
                $data = [
                    'message' => $message,
                    'friend' => $userData,
                    'status' => $status,
                ];
            }else{
                $data = [
                    'message' => 'Friend data not found',
                    'friend' => $user   ,
                    'status' => 404,
                ];
            }

        }catch (ValidationException $validationException){
            $data = [
                'errorMessage' => $validationException->errors(),
                'status' => $validationException->status,
            ];
            Log::critical(json_encode($data, $validationException->status));
            return response()->json($data, 200);
        } catch (\Exception $exception){
            $status = 500;
            $data = [
                'action' => 'Add Friend',
                'exception' => $exception->getMessage(),
                'params' => $request->all(),
                'status' => $status,
            ];
            Log::critical(json_encode($data));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Add Friends';
            $log['request_parameter'] = $request->all();
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
            return response()->json($data,$status);
        }
        return response()->json($data, 200);
    }

    public function inviteFriend(Request $request){
        try{
            $this->validate($request,[
                'friend' => 'required',
                'friend.firstName' => 'required|string|max:255',
                'friend.lastName' => 'required|string|max:255',
                'friend.mobile' => 'required|integer|min:1111111111|max:9999999999|regex:/[0-9]/|unique:users,mobile_no',
            ]);
            $appUrl = 'www.google.com';
            $friend = $request['friend'];
            $firstName = $friend['firstName'];
            $lastName = $friend['lastName'];
            $mobile = $friend['mobile'];

            $sms = 'Hi '.$firstName.' '.$lastName.', '.Auth::user()->first_name.' '.Auth::user()->last_name.' invites you on Combine. Download it from '.$appUrl;

            $fields_string = '';
            $url = 'http://jumbosms.shlrtechnosoft.com/websms/sendsms.aspx';
            $userid = "mayankmodi";
            $password = "mayank@051";
            $sender = "ROKWAY";


            $fields = array(
                'userid' => urlencode($userid),
                'password' => urlencode($password),
                'sender' => urlencode($sender),
                'mobileno' => urlencode($mobile),
                'msg' => urlencode($sms),
            );
            //url-ify the data for the POST
            foreach($fields as $key=>$value) {
                $fields_string .= $key.'='.$value.'&';
            }
            rtrim($fields_string, '&');

            //open connection
            $ch = curl_init();
            //set the url, number of POST vars, POST data
            curl_setopt($ch,CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_POST, count($fields));
            curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
            //execute post
            $result = curl_exec($ch);
            //close connection
            curl_close($ch);

            if($result)
            {
                $message = 'Friend invited successfully';
                $status = 200;

            }else {
                $message = 'Unable to invited Friend';
                $status = 200;
            }
            $data = [
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
        } catch (\Exception $exception){
            $status = 500;
            $data = [
                'action' => 'Invite Friend',
                'exception' => $exception->getMessage(),
                'params' => $request->all(),
                'status' => $status,
            ];
            Log::critical(json_encode($data));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Invite Friends';
            $log['request_parameter'] = $request->all();
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
            return response()->json($data,$status);
        }
        $response = [
            'data' => $data
        ];
        return response()->json($response, 200);
    }

}
