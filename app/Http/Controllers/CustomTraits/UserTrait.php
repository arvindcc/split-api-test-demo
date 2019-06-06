<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 29/12/18
 * Time: 10:36 AM
 */

namespace App\Http\Controllers\CustomTraits;


use App\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use App\Log as CustomLog;


trait UserTrait
{

    protected function saveAvatar(String $base64_image)
    {
        try{
            $filename = '';
            if($base64_image != null){
                $avatarImage = base64_decode($base64_image);

                $imageUploadPath = env('AVATAR_PATH');

                if (!file_exists($imageUploadPath)) {
                    File::makeDirectory($imageUploadPath, $mode = 0777, true, true);
                }
                $filename = mt_rand(1,10000000000).sha1(time()).".png";
                File::put($imageUploadPath.$filename, $avatarImage);

            }
            Log::critical(json_encode($filename));
            $log = new CustomLog();
            $log['user_id'] = 0;
            $log['action'] = 'Save Avatar Picture';
            $log['request_parameter'] = $base64_image;
            $log['exception'] = 'successfully';
            $log['status_code'] = 200;
            $log->save();
            return $filename;

        }catch (\Exception $exception){
            $status = 500;
            $data = [
                'action' => 'Save Avatar Picture',
                'exception' => $exception->getMessage(),
            ];
            Log::critical(json_encode($data));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Save Avatar Picture';
            $log['request_parameter'] = $base64_image;
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
            return response()->json($data, $status);
        }
    }

    protected function getUserData($user){
        try{
            $userData['userId'] = $user['id'];
            $userData['firstName'] = $user['first_name'];
            $userData['lastName'] = $user['last_name'];
            $userData['email'] = $user['email'];
            $userData['mobile'] = $user['mobile_no'];
            $avatar = $user['avatar'] == null ? File::get(env('DEFAULT_AVATAR_PATH')) : File::get(env('AVATAR_PATH').$user['avatar']);
            $filename =  explode('.', $user['avatar'] == null ? 'default.png' : $user['avatar']);
            $userData['avatar'] = 'data:image/'.$filename[1].';base64,'.base64_encode($avatar);
            $userData['transactionCount'] = $user['transaction_count'];
            Log::critical(json_encode($userData));
            return $userData;
        }catch (\Exception $exception){
            $status = 500;
            $data = [
                'action' => 'Get User Data',
                'exception' => $exception->getMessage(),
            ];
            Log::critical(json_encode($data));
            $log = new CustomLog();
            $log['user_id'] = 0;
            $log['action'] = 'Get User Data';
            $log['request_parameter'] = $user;
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
            return response()->json($data, $status);
        }
    }

    protected function incrementTransactionCount($userId){
        try{
            $user = User::where('id', $userId)->first();

            $transactionCount = $user['transaction_count'];

            $user->update([
               'transaction_count' => $transactionCount + 1,
            ]);

        }catch (\Exception $exception){
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Increment Transaction Count';
            $log['request_parameter'] = $userId;
            $log['exception'] = $exception->getMessage();
            $log['status_code'] = 500;
            $log->save();
            throw new ModelNotFoundException($exception->getMessage());
        }
    }


}
