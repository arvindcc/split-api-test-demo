<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 23/12/18
 * Time: 9:53 PM
 */

namespace App\Http\Controllers\Auth;

use App\Activity;
use App\Bill;
use App\Group;
use App\GroupBalance;
use App\GroupUser;
use App\Http\Controllers\CustomTraits\UserTrait;
use App\Otp;
use App\Transaction;
use App\User;
use App\UserHasFriend;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Routing\Controller as BaseController;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Log as CustomLog;


class AuthController extends BaseController
{
    use UserTrait;

    public function __construct(){
        $this->middleware('jwt.auth',['only' => ['logout']]);
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }
    public function login(Request $request){
        try{
            $this->validate($request, [
                 'email' => 'required|string|email|max:255|',
                 'password' => 'required|string|min:6',
             ]);
            $credentials = $request->only('email','password');
            $userData =  array();
            if($token = JWTAuth::attempt($credentials)){
                $user = Auth::user();
                $userData = $this->getUserData($user);
                $userData["transactionCount"] = -1;
                $message = "Logged in successfully!!";
                $status = 200;
            }else{
                $message = "Invalid credentials";
                $status = 401;
            }

        }
        catch (ValidationException $validationException){
            $data = [
                'errorMessages' => $validationException->errors(),
                'status' => $validationException->status
            ];
            Log::critical(json_encode($data));
            return response()->json($data,200);
        }
        catch (\Exception $e){
            $message = "Fail";
            $status = 500;
            $userData =  array();
            $token = '';
            $data = [
                'action' => 'Login',
                'exception' => $e->getMessage(),
                'params' => $request->all(),
                'status' => $status,
                'token' => $token,
                'userData' => $userData,
                'message' => $message

            ];
            Log::critical(json_encode($data));
            $log = new CustomLog();
            $log['user_id'] = 0;
            $log['action'] = 'Login';
            $log['request_parameter'] = $request->all();
            $log['exception'] = $e->getMessage();
            $log['status_code'] = 500;
            $log->save();
            return response()->json($data,$status);
        }
        $response = [
            'userData' => $userData,
            'token' => $token,
            'message' => $message,
            'status' => $status
        ];
        return response()->json($response,200);
    }

    public function getOtp(Request $request){
        try{
            $message = '';
            $status = null;
            $data = array();
            $this->validate($request, [
                'mobile' => 'required|integer|min:1111111111|max:9999999999|regex:/[0-9]/',
                'type' => 'required|string|max:255',
            ]);
            $mobile_no = $request['mobile'];
            $type = $request['type'];
            $user = User::where('mobile_no', $mobile_no)->first();
            if ($user != null && $type == 'signup') {
              $message = "User already exist for this mobile number.";
              $data = [
                  'message' => $message,
              ];
              return response()->json($data,409);
            } elseif ($user == null && $type == 'forgotpassword') {
              $message = "User doesn't exist for this mobile number.";
              $data = [
                  'message' => $message,
              ];
              return response()->json($data,404);
            } elseif ($mobile_no == null) {
                $message = "Please Enter a Valid Mobile No.";
                $status = 412;
            }else{
                $otp = $this->generateOtp();

                $fields_string = '';

                $url = 'http://jumbosms.shlrtechnosoft.com/websms/sendsms.aspx';
                $userid = "mayankmodi";
                $password = "mayank@051";
                $sender = "ROKWAY";
                $sms = rawurlencode('Your Otp is '.$otp);

                $fields = array(
                    'userid' => urlencode($userid),
                    'password' => urlencode($password),
                    'sender' => urlencode($sender),
                    'mobileno' => urlencode($mobile_no),
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
                    $status = 200;
                    $message = "Sms sent successfully";
                    $otpGen = new Otp();
                    $otpGen['mobile_no'] = $mobile_no;
                    $otpGen['otp'] = $otp;
                    $otpGen->save();
                    $data = [
                        'message' => $message,
                        'status' => $status
                    ];

                }
            }
        }
        catch (ValidationException $validationException){
            $data = [
                'errorMessages' => $validationException->errors(),
                'status' => $validationException->status
            ];
            return response()->json($data,200);
        }
        catch (\Exception $e){
            $message= "Fail";
            $status = 500;
            $data = [
                'action' => 'get otp',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            $log = new CustomLog();
            $log['user_id'] = 0;
            $log['action'] = 'get otp';
            $log['request_parameter'] = $request->all();
            $log['exception'] = $e->getMessage();
            $log['status_code'] = 500;
            $log->save();
            Log::critical(json_encode($data, $status));
        }
        $response = [
            'data' => $data,
        ];
        return response()->json($response,200);
    }

    public function generateOtp(){
        try{

            $OtpCode = rand(1111,9999);
            return $OtpCode;

        }catch(\Exception $e){
            $data = [
                'action' => 'generateOtp',
                'exception' => $e->getMessage(),
            ];
            $log = new CustomLog();
            $log['user_id'] = 0;
            $log['action'] = 'Create Group';
            $log['request_parameter'] = null;
            $log['exception'] = $e->getMessage();
            $log['status_code'] = 500;
            $log->save();
            Log::critical(json_encode($data));
        }

    }

    public function verifyOtp(Request $request){
        try{
            $this->validate($request, [
                'mobile' => 'required|integer|min:1111111111|max:9999999999|regex:/[0-9]/',
                'otp' => 'required|string|min:4|max:4'
            ]);
            $mobile_no = $request['mobile'];
            $userOtp = $request['otp'];
            $otp = Otp::where('mobile_no',$mobile_no)->orderBy('id','desc')->first();
            if($otp['otp'] == $userOtp) {
                $message = "Valid Otp";
                $status = 200;
            }else{
                $message = "Invalid Otp...Please Enter Correct Otp";
                $status = 412;
            }

        }
        catch (ValidationException $validationException){
            $data = [
                'errorMessages' => $validationException->errors(),
                'status' => $validationException->status
            ];
            return response()->json($data,200);
        }
        catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'verify otp',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];

            Log::critical(json_encode($data,$status));
            $log = new CustomLog();
            $log['user_id'] = 0;
            $log['action'] = 'Verify Otp';
            $log['request_parameter'] = $request->all();
            $log['exception'] = $e->getMessage();
            $log['status_code'] = 500;
            $log->save();
        }
        $response = [
            'message' => $message,
            'status' => $status,
        ];

        return response()->json($response,200);
    }

    public function register(Request $request){
        try{
            $this->validate($request, [
                'firstName' => 'required|string|max:255',
                'lastName' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,',
                'password' => 'required|string|min:6',
                'mobile' => 'required|integer|min:1111111111|max:9999999999|regex:/[0-9]/|unique:users,mobile_no,',
                'otp' => 'required|string|min:4|max:4'
             ]);
            $token = '';
            $userData = array();
            $user = $this->createUser($request->all());
            if($user != null){
                $credentials = $request->only('email','password');
                if($token = JWTAuth::attempt($credentials)){
                    $user = Auth::user();
                    $userData = $this->getUserData($user);
                    $message = "Signup in successfully!!";
                    $status = 200;
                }else{
                    $message = "Invalid credentials";
                    $status = 401;
                }
            }else{
                $message = "Unable to register";
                $status = 401;
            }

        }
        catch (ValidationException $validationException){
            $data = [
                'errorMessages' => $validationException->errors(),
                'status' => $validationException->status
            ];
            return response()->json($data,200);
        }
        catch (\Exception $e){
            $status = 500;
            $userData =  array();
            $token = '';
            $data = [
                'action' => 'Register API',
                'exception' => $e->getMessage(),
                'params' => $request->all(),
                'status' => $status,
                'userData' => $userData,
                'token' => $token
            ];
            Log::critical(json_encode($data));
            $log = new CustomLog();
            $log['user_id'] = 0;
            $log['action'] = 'Register API';
            $log['request_parameter'] = json_encode($request->json());
            $log['exception'] = $e->getMessage();
            $log['status_code'] = 500;
            $log->save();
            return response()->json($data,$status);
        }
        $response = [
            'message' => $message,
            'token' => $token,
            'userData' => $userData,
            'status' => $status
        ];
        return response()->json($response, 200);
    }

/*    protected function saveAvatar(String $base64_image, String $mobileNo)
    {
        try{
            $filename = '';
            if($base64_image != null && $mobileNo != null){
                $avatarImage = base64_decode($base64_image);

                $sha1UserId = sha1($mobileNo);
                $imageUploadPath = env('AVATAR_PATH').$sha1UserId.DIRECTORY_SEPARATOR;

                if (!file_exists($imageUploadPath)) {
                    File::makeDirectory($imageUploadPath, $mode = 0777, true, true);
                }
                $filename = mt_rand(1,10000000000).sha1(time()).".png";
                File::put($imageUploadPath.$filename, $avatarImage);
            }

            return $filename;

        }catch (\Exception $e){
            $data = [
                'action' => 'Save Avatar Picture',
                'exception' => $e->getMessage(),
            ];
            Log::critical(json_encode($data));
        }
    }*/

    protected function createUser(array $input)
    {
        try{
            $mobile_no = $input['mobile'];
            $userOtp = $input['otp'];
            $otpTable = Otp::where('mobile_no',$mobile_no)->orderBy('id','desc')->first();
            if($otpTable['otp'] == $userOtp){
                $filename = $this->saveAvatar($input['avatar']);
                $user = User::create([
                    'first_name' => $input['firstName'],
                    'last_name' => $input['lastName'],
                    'mobile_no' => $input['mobile'],
                    'email' => $input['email'],
                    'avatar' => $filename,
                    'password' => Hash::make($input['password']),
                    'transaction_count' => 0,
                    'device_token' => '',
                ]);
            }else{
                $message = 'Please enter a valid Otp';
                $data = [
                  'message' => $message,
                ];
                Log::critical(json_encode($data));
                return null;
            }
        }catch(\Exception $e){
            $user = array();
            $data = [
                'action' => 'Create User',
                'exception' => $e->getMessage(),
                'params' => $input
            ];
            Log::critical(json_encode($data));
            $log = new CustomLog();
            $log['user_id'] = 0;
            $log['action'] = 'Create User';
            $log['request_parameter'] = json_encode($input);
            $log['exception'] = $e->getMessage();
            $log['status_code'] = 500;
            $log->save();
        }
        return $user;
    }

    public function logout(Request $request){
        try{
            $this->validate($request, [
                'token' => 'required|string',
            ]);
            $token = $request['token'];
            if(JWTAuth::invalidate($token)){
                $message = "Logout Successfully";
                $status = 200;
            }else{
                $message = "Sorry Can't Logout";
                $status = 500;
            }
        }
        catch (ValidationException $validationException){
            $data = [
                'errorMessages' => $validationException->errors(),
                'status' => $validationException->status
            ];
            return response()->json($data,200);
        }
        catch (\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'logout',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
            $log = new CustomLog();
            $log['user_id'] = 0;
            $log['action'] = 'Logout';
            $log['request_parameter'] = $request->all();
            $log['exception'] = $e->getMessage();
            $log['status_code'] = 500;
            $log->save();
        }
        $response = [
            'message' => $message,
            'token' => $token
        ];
        return response()->json($response,200);
    }

    public function forgotPassword(Request $request){
        try{
            $this->validate($request, [
                'password' => 'required|string|min:6',
                'mobile' => 'required|integer|min:1111111111|max:9999999999|regex:/[0-9]/',
                'otp' => 'required|string|min:4|max:4'
            ]);
            $user = User::where('mobile_no', $request['mobile'])->first();
            $mobile_no = $request['mobile'];
            $userOtp = $request['otp'];
            $otpTable = Otp::where('mobile_no',$mobile_no)->orderBy('id','desc')->first();
            if($user && $otpTable['otp'] == $userOtp ){
                $user->update([
                    'password' => Hash::make($request['password'])
                ]);
                $message = "Password Changed Successfully";
                $status = 200;
            }else{
                $message = "Please Enter a Valid Mobile No.!!";
                $status = 401;
            }
        }
        catch (ValidationException $validationException){
            $data = [
                'errorMessages' => $validationException->errors(),
                'status' => $validationException->status
            ];
            return response()->json($data,200);
        }
        catch (\Exception $e){
            $status = 500;
            $data = [
                'action' => 'Forgot Password',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
            $log = new CustomLog();
            $log['user_id'] = 0;
            $log['action'] = 'Forgot Password';
            $log['request_parameter'] = $request->all();
            $log['exception'] = $e->getMessage();
            $log['status_code'] = 500;
            $log->save();
            return response()->json($data,$status);
        }
        $response = [
            'message' => $message,
            'status' => $status,
        ];
        return response()->json($response,200);
    }


    public function checkVersion(Request $request){
        try{
            $this->validate($request, [
                'version' => 'required|string'
            ]);
            $version = $request['version'];
            $versionServer = '1.0';
            if($version == $versionServer) {
                $message = "Latest Version";
                $status = 200;
            }else{
                $message = "Old Version";
                $status = 412;
            }

        }
        catch (ValidationException $validationException){
            $data = [
                'errorMessages' => $validationException->errors(),
                'status' => $validationException->status
            ];
            return response()->json($data,200);
        }
        catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Check Version',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];

            Log::critical(json_encode($data,$status));
            $log = new CustomLog();
            $log['user_id'] = 0;
            $log['action'] = 'Check Version';
            $log['request_parameter'] = $request->all();
            $log['exception'] = $e->getMessage();
            $log['status_code'] = 500;
            $log->save();
        }
        $response = [
            'message' => $message,
            'status' => $status,
        ];

        return response()->json($response,200);
    }
}
