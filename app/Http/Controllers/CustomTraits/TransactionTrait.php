<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 1/12/18
 * Time: 4:21 PM
 */

namespace App\Http\Controllers\CustomTraits;

use App\Bill;
use App\GroupBalance;
use App\Http\Controllers\CustomTraits\ActivityTrait;

use App\Payment;
use App\Transaction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use App\Log as CustomLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait TransactionTrait
{
    use BillTrait;
    use ActivityTrait;
    use GroupTrait;

    protected function syncTransaction($transaction, $userHasFriendId, $billId){
        try{
            $transactionType = $transaction['type'];
            $syncTransaction = new Transaction();
            $syncTransaction['transactionId'] = $transaction['transactionId'];
            $syncTransaction['transaction_type'] = $transaction['type'];
            $syncTransaction['added_by_user_id'] = $transaction['addedByUserId'];
            $syncTransaction['group_id'] = $transaction['groupId'] == null ? null : $transaction['groupId'];
            $syncTransaction['user_friends_id'] = $userHasFriendId;
            $syncTransaction['bill_id'] = $billId == null ? null : $billId;
            $syncTransaction['note'] = $transaction['note'];
            $syncTransaction['image'] = $transaction['image'] == null ? '' : $this->saveImage($transaction['image']);
            $syncTransaction['added_on'] = Carbon::createFromTimeString($transaction['addedOn']);
            $syncTransaction['created_at'] = Carbon::createFromTimeString($transaction['date']);
            switch ($transactionType){
                case 'payment':
                    $syncTransaction->save();
                    $this->syncPayment($syncTransaction['id'], $userHasFriendId, $transaction['userOwe'], $transaction['userPaid'], $transaction['icon'], $transaction['amount']);
                    if($transaction['groupId']){
                        $this->calcGroupBalance($transaction['userOwe'], $transaction['userPaid'], $transaction['amount'], $transaction['groupId'], 'add');
                    }
                    break;

                case 'bill':
                    $syncTransaction->save();
                    $billUsers = $transaction['billUsers'];
                    $billTransactions = $transaction['transactions'];
                    $this->syncBillUser($billUsers, $syncTransaction['id'], $billId);
                    $this->syncBillTransaction($billTransactions, $syncTransaction['id'], $billId, $transaction['groupId']);

                    break;

                case 'group':
                    break;

                default:
                    break;
            }
        }catch (\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('syncTransaction: '.$exception->getMessage());
        }

    }

    protected function syncPayment($transactionId, $userHasFriendId, $userOwe, $userPaid, $icon, $amount){
        try{
            $payment = new Payment();
            $payment['user_owe_id'] = $userOwe;
            $payment['user_paid_id'] = $userPaid;
            $payment['transaction_id'] = $transactionId;
            $payment['user_friends_id'] = $userHasFriendId;
            $payment['icon'] = $icon;
            $payment['payment_amount'] = $amount;

            return $payment->save() ? $payment : null;


        }catch(\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('Fail to add Payment');
        }
    }

    protected function saveImage(String $unformattedBase64_image){
        try{
            $filename = '';
            if(strpos($unformattedBase64_image, 'http')!== false){
                $splitedImageUrl = explode('/', $unformattedBase64_image);
                $splitedImageUrlSize = sizeof($splitedImageUrl);
                $filename = $splitedImageUrl[$splitedImageUrlSize-1];
            }else{
                $formattedBase64 = explode(';base64,', $unformattedBase64_image);
                if(count($formattedBase64) > 0){
                    $base64_image = $formattedBase64[1];
                    $avatarImage = base64_decode($base64_image);
                    $imageUploadPath = env('TRANSACTION_IMAGE_PATH');

                    if (!file_exists($imageUploadPath)) {
                        File::makeDirectory($imageUploadPath, $mode = 0777, true, true);
                    }
                    $filename = mt_rand(1,10000000000).sha1(time()).".png";
                    File::put($imageUploadPath.$filename, $avatarImage);
                }
            }

            Log::critical(json_encode($filename));
            $log = new CustomLog();
            $log['user_id'] = Auth::user()->id;
            $log['action'] = 'Save Transaction Picture';
            $log['request_parameter'] = $unformattedBase64_image;
            $log['exception'] = 'Successfully';
            $log['status_code'] = 200;
            $log->save();
            return $filename;

        }catch (\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('Fail to save transaction Image');
        }
    }

    protected function  updateSyncTransactionTypePayment($transaction){
        try{
            $image = $transaction['image'] == null ? '' : $this->saveImage($transaction['image']);
            Transaction::where('transactionId', $transaction['transactionId'])
                        ->update([
                           'added_by_user_id' => $transaction['addedByUserId'],
                           'note' => $transaction['note'],
                           'image' => $image,
                           'transaction_type' => $transaction['type'],
                           'group_id' => $transaction['groupId'],
                            'added_on' => Carbon::createFromTimeString($transaction['addedOn']),
                        ]);
            $payment = Transaction::where('transactionId', $transaction['transactionId'])->first()->payment->first();
            if($transaction['groupId']){
                $this->calcGroupBalance($payment['user_owe_id'], $payment['user_paid_id'], $payment['payment_amount'], $transaction['groupId'], 'delete');
            }
            if($payment != null){
                Payment::where('id',$payment['id'])
                    ->update([
                        'user_owe_id' => $transaction['userOwe'],
                        'user_paid_id' => $transaction['userPaid'],
                        'payment_amount' => $transaction['amount'],
                        'icon' => $transaction['icon'],
                    ]);
            }
            if($transaction['groupId']){
                $this->calcGroupBalance($transaction['userOwe'], $transaction['userPaid'], $transaction['amount'], $transaction['groupId'], 'add');
            }
        }catch (\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('updateSyncTransactionTypePayment: '.$exception->getMessage());
        }
    }

    protected function updateSyncTransactionTypeBill($transaction, $oldTransaction, $bill){
        try{
            $newBillIcon = $transaction['icon'];
            $newBillUser = $transaction['billUsers'];
            $oldBillUsers = $oldTransaction->billUsers;

            $newBillTransaction = $transaction['transactions'];
            $oldBillTransactions = $oldTransaction->billTransactions;


            $image = $transaction['image'] == null ? '' : $this->saveImage($transaction['image']);
            $oldTransaction->update([
                    'added_by_user_id' => $transaction['addedByUserId'],
                    'note' => $transaction['note'],
                    'image' => $image,
                    'transaction_type' => $transaction['type'],
                    'group_id' => $transaction['groupId'],
                    'added_on' => Carbon::createFromTimeString($transaction['addedOn']),
                ]);

            $bill->billIcon->update([
                'name' => $newBillIcon['name'],
                'icon_name' => $newBillIcon['iconName'],
                'type' => $newBillIcon['type'],
                'icon_bg_color' => $newBillIcon['iconBackgroundColor'],

            ]);

            $this->syncUpdateBillUser($oldBillUsers, $newBillUser);

            $this->syncUpdateBillTransaction($oldBillTransactions, $newBillTransaction, $oldTransaction['group_id']);



        }catch (\Exception $exception){
            DB::rollBack();
            throw new ModelNotFoundException('updateSyncTransactionTypeBill: '.$exception->getMessage());
        }
    }

}
