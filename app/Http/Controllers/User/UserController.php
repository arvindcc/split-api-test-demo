<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 29/12/18
 * Time: 11:31 AM
 */

namespace App\Http\Controllers\User;

use App\Http\Controllers\CustomTraits\UserTrait;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Routing\Controller as BaseController;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\JWT;
use App\Log as CustomLog;


class UserController extends  BaseController
{
    use UserTrait;

    public function __construct(){
        $this->middleware('jwt.auth');
        if(!Auth::guest()){
            $this->user = Auth::user();
        }
    }

    public function updateAvatar(Request $request){
        try{
            $this->validate($request,[
                'avatar' => 'required|string',
            ]);
            $user = Auth::user();
            $filename = $this->saveAvatar($request['avatar']);
            if($filename != null){
                $user->update([
                    'avatar' => $filename,
                ]);
                Log::critical(json_encode($user));
                $message = 'Avatar updated Successfully';
                $status = 200;
                $userData = $this->getUserData($user);
                $data = [
                    'message' => $message,
                    'userData' => $userData,
                    'status' => $status,
                ];
            }else{
                $message = 'Sorry Cannot update the Avatar';
                $status = 500;
                $data = [
                    'message' => $message,
                    'status' => $status,
                ];
            }

        }
        catch (ValidationException $validationException){
            $data = [
                'errorMessage' => $validationException->errors(),
                'status' => $validationException->status,
            ];
            Log::critical(json_encode($data, $validationException->status));
            return response()->json($data, 200);
        }
        catch (\Exception $exception){
            $status = 500;
            $data = [
                'action' => 'Update Avatar',
                'exception' => $exception->getMessage(),
                'params' => $request->all(),
                'status' => $status,
            ];
            Log::critical(json_encode($data));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Update Avatar';
            $log['request_parameter'] = $request->all();
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
            return response()->json($data,$status);
        }
        return response()->json($data, 200);
    }

    public function updateAccount(Request $request){
        try{
            $this->validate($request,[
                'firstName' => 'required|string|max:255',
                'lastName' => 'required|string|max:255',
                'mobile' => 'required|integer|min:1111111111|max:9999999999|regex:/[0-9]/|unique:users,mobile_no,'.Auth::user()->id,
            ]);
            $user = Auth::user();
            if($user != null){
                $updateStatus = $user->update([
                    'first_name' => $request['firstName'],
                    'last_name' => $request['lastName'],
                    'mobile_no' => $request['mobile'],
                ]);
                if($updateStatus == true){
                    $message = 'Account updated Successfully';
                    $status = 200;
                    $userData = $this->getUserData($user);
                }else{
                    $message = 'Sorry Cannot updated Account';
                    $status = 500;
                    $userData = '';
                }

                $data = [
                    'message' => $message,
                    'userData' => $userData,
                    'status' => $status,
                ];
            }else{
                $message = 'Unauthorized User';
                $status = 401;
                $data = [
                    'message' => $message,
                    'status' => $status,
                ];
            }

        }
        catch (ValidationException $validationException){
            $data = [
                'errorMessage' => $validationException->errors(),
                'status' => $validationException->status,
            ];
            Log::critical(json_encode($data, $validationException->status));
            return response()->json($data, 200);
        }
        catch (\Exception $exception){
            $status = 500;
            $data = [
                'action' => 'Update Account',
                'exception' => $exception->getMessage(),
                'params' => $request->all(),
                'status' => $status,
            ];
            Log::critical(json_encode($data));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Update Account';
            $log['request_parameter'] = $request->all();
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
            return response()->json($data,$status);
        }
        return response()->json($data, 200);
    }

    public function updateEmail(Request $request){
        try{
            $this->validate($request,[
                'email' => 'required|string|email|max:255'.Auth::user()->id,
                'password' => 'required|string|min:6',
                'token' => 'string',
            ]);
            $user = Auth::user();
            $token = $request['token'];
            if($user != null){
                $updateStatus = $user->update([
                    'email' => $request['email'],
                    'password' => Hash::make($request['password']),
                ]);
                if($updateStatus == true){
                    JWTAuth::invalidate($request['token']);
                    $credentials = $request->only('email','password');
                    $token = JWTAuth::attempt($credentials);
                    $message = 'Email Id updated Successfully';
                    $status = 200;
                    $userData = $this->getUserData($user);
                }else{
                    $message = 'Sorry Cannot updated Email Id';
                    $status = 500;
                    $userData = '';
                }
                $data = [
                    'message' => $message,
                    'userData' => $userData,
                    'token' => $token,
                    'status' => $status,
                ];
            }else{
                $message = 'Unauthorized User';
                $status = 401;
                $data = [
                    'message' => $message,
                    'status' => $status,
                ];
            }

        }
        catch (ValidationException $validationException){
            $data = [
                'errorMessage' => $validationException->errors(),
                'status' => $validationException->status,
            ];
            Log::critical(json_encode($data, $validationException->status));
            return response()->json($data, 200);
        }
        catch (\Exception $exception){
            $status = 500;
            $data = [
                'action' => 'Update Email',
                'exception' => $exception->getMessage(),
                'params' => $request->all(),
                'status' => $status,
            ];
            Log::critical(json_encode($data));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Update Email';
            $log['request_parameter'] = $request->all();
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
            return response()->json($data,$status);
        }
        return response()->json($data, 200);
    }

    public function updatePassword(Request $request){
        try{
            $this->validate($request,[
                'oldPassword' => 'required|string|min:6',
                'newPassword' => 'required|string|min:6',
                'token' => 'string',
            ]);
            $user = Auth::user();
            $token = $request['token'];
            if($user != null){
                $updateStatus = $user->update([
                    'password' => Hash::make($request['newPassword']),
                ]);
                if($updateStatus == true){
                    JWTAuth::invalidate($request['token']);
                    $token = JWTAuth::attempt([
                        'email' => $user['email'],
                        'password' => $request['newPassword']
                    ]);
                    $message = 'Password updated Successfully';
                    $status = 200;
                }else{
                    $message = 'Sorry Cannot updated Password';
                    $status = 500;
                    $userData = '';
                }
                $data = [
                    'message' => $message,
                    'userData' => $userData,
                    'token' => $token,
                    'status' => $status,
                ];
            }else{
                $message = 'Unauthorized User';
                $status = 401;
                $data = [
                    'message' => $message,
                    'status' => $status,
                ];
            }

        }
        catch (ValidationException $validationException){
            $data = [
                'errorMessage' => $validationException->errors(),
                'status' => $validationException->status,
            ];
            Log::critical(json_encode($data, $validationException->status));
            return response()->json($data, 200);
        }
        catch (\Exception $exception){
            $status = 500;
            $data = [
                'action' => 'Update Password',
                'exception' => $exception->getMessage(),
                'params' => $request->all(),
                'status' => $status,
            ];
            Log::critical(json_encode($data));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Find Password';
            $log['request_parameter'] = $request->all();
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
            return response()->json($data,$status);
        }
        return response()->json($data, 200);
    }

    public function findFriends(Request $request){
        try{
            $this->validate($request,[
                'contacts' => 'required',
                'token' => 'string',
            ]);
            $contacts = $request['contacts'];
            $updatedContacts = array();
            $iterartor = 0;
            foreach ($contacts as  $contact){
                foreach ($contact['mobile'] as  $mobileNo){
                    $friend = array();
                    $isFriendAvailable = User::where('mobile_no', $mobileNo)->first();
                    if($isFriendAvailable != null){
                        $friend['firstName'] = $contact['firstName'];
                        $friend['lastName'] = $contact['lastName'];
                        $friend['mobile'] = $mobileNo;
                        $friend['avatar'] = env('APP_URL').env('AVATAR_PATH').User::where('mobile_no', $mobileNo)->pluck('avatar');
                        $friend['type'] = 'addFriend';
                    }else{
                        $friend['firstName'] = $contact['firstName'];
                        $friend['lastName'] = $contact['lastName'];
                        $friend['mobile'] = $mobileNo;
                        $friend['avatar'] = env('APP_URL').env('DEFAULT_AVATAR_PATH');
                        $friend['type'] = 'inviteFriend';
                    }
                    $updatedContacts[$iterartor] = $friend;
                    $iterartor++;
                }
            }
            $data = [
                'friends' => $updatedContacts,
                'status' => 200,
            ];
        }catch (\Exception $exception){
            $status = 500;
            $message = $exception->getMessage();
            $data = [
              'action' => 'Find Friends',
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

}