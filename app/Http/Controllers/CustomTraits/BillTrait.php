<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17/2/19
 * Time: 2:05 PM
 */

namespace App\Http\Controllers\CustomTraits;


use App\Bill;
use App\BillIcon;
use App\BillTransaction;
use App\BillUser;
use App\UserHasFriend;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

trait BillTrait
{
    protected function syncBillIcon($iconInfo)
    {
        try{
            $billIcon = new BillIcon();
            $billIcon['type'] = $iconInfo['type'];
            $billIcon['icon_name'] = $iconInfo['iconName'];
            $billIcon['name'] = $iconInfo['name'];
            $billIcon['icon_bg_color'] = $iconInfo['iconBackgroundColor'];

            return $billIcon->save() ? $billIcon : null;
        }catch(\Exception $exception){
            DB::rollBack();
            throw  new ModelNotFoundException('Fail to add Bill Icon');

        }
    }

    protected function syncBill($description, $splitOptionType, $billIconId, $billAmount)
    {
        try{
            $bill = new Bill();
            $bill['description'] = $description;
            $bill['split_option_type'] = $splitOptionType;
            $bill['bill_icon_id'] = $billIconId;
            $bill['bill_amount'] = $billAmount;
            $bill->save();
            return $bill;
        }catch(\Exception $exception){
            DB::rollBack();
            throw  new ModelNotFoundException('syncBill'.$exception->getMessage());
            
        }
    }

    protected function syncBillUser($users, $transactionId, $billId)
    {
        try{
            foreach ($users as $user) {

                $billUser = new BillUser();
                $billUser['user_id'] = $user['userId'];
                $billUser['user_owe'] = $user['userOwe'];
                $billUser['user_paid'] = $user['userPaid'];
                $billUser['user_in'] = $user['userIn'];
                $billUser['transaction_id'] = $transactionId;
                $billUser['bill_id'] = $billId;
                $billUser->save();
            }

        }catch(\Exception $exception){
            DB::rollBack();
            throw  new ModelNotFoundException('syncBillUser'.$exception->getMessage());
        }
    }

    protected function syncBillTransaction($transactions, $transactionId, $billId, $groupId)
    {
        try{
            foreach ($transactions as $transaction) {

                $billTransaction = new BillTransaction();
                $billTransaction['user_owe_id'] = $transaction['userOwe'];
                $billTransaction['user_paid_id'] = $transaction['userPaid'];
                $billTransaction['amount'] = $transaction['amount'];
                $billTransaction['transaction_id'] = $transactionId;
                $billTransaction['bill_id'] = $billId;
                $billTransaction->save();

                if($groupId){
                    $this->calcGroupBalance($transaction['userOwe'], $transaction['userPaid'], $transaction['amount'], $groupId, 'add');
                }
        }

        }catch(\Exception $exception){
            DB::rollBack();
            throw  new ModelNotFoundException('syncBillTransaction'.$exception->getMessage());

        }
    }

    protected function syncUpdateBillUser($oldUsers, $newUsers)
    {
        try{

            foreach ($newUsers as $key => $newUser){
                if($oldUsers[$key]['user_id'] == $newUser['userId']){

                    BillUser::where('id', $oldUsers[$key]['id'])->first()->update([
                        'user_owe' => $newUser['userOwe'],
                        'user_paid' => $newUser['userPaid'],
                        'user_in' => $newUser['userIn']
                    ]);
                }

            }

        }catch(\Exception $exception){
            DB::rollBack();
            throw  new ModelNotFoundException($exception->getMessage());

        }
    }

    protected function syncUpdateBillTransaction($oldTransactions, $newTransactions, $groupId)
    {
        try{
            foreach ($newTransactions as $key => $newTransaction){

                BillTransaction::where('id', $oldTransactions[$key]['id'])->first()->update([
                    'user_owe_id' => $newTransaction['userOwe'],
                    'user_paid_id' => $newTransaction['userPaid'],
                    'amount' => $newTransaction['amount']
                ]);

            }
        }catch(\Exception $exception){
            DB::rollBack();
            throw  new ModelNotFoundException('syncUpdateBillTransaction'.$exception->getMessage());

        }
    }

}