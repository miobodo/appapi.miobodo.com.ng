<?php

namespace App\Http\Controllers;

use App\Models\NotificationModel;
use App\Models\User;
use Illuminate\Http\Request;

class GenerateController extends Controller
{
    // Generate Promo Code 6 Alphabeth
    public function GeneratePromoCode()
    {
        do {
            $promo_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);
        } while (User::where('promo_code', $promo_code)->exists());
        return $promo_code;
    }

    // Generate Notification Reference
    public function GenerateNotificationReference()
    {
        do {
            $notification_reference = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 15);
        } while (NotificationModel::where('ref', $notification_reference)->exists());
        return $notification_reference;
    }

    // Generate One Time Password
    public function GenerateOTP($cus_id)
    {

        $otp = rand(1000, 9999);
        $hash = password_hash($otp, PASSWORD_DEFAULT);
        // Update OTP and updated at
        User::where('id', $cus_id)->update([
            'verification_otp' => $hash,
            'otp_created_at' => now()
        ]);
        return $otp;

    }


    // Generate Virtual Account Reference
    // public function GenerateVirtualAccountReference()
    // {
    //     do {
    //         $ref = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 15);
    //     } while (VirtualAccountsModel::where('account_reference', $ref)->exists());
    //     return $ref;
    // }


    // Generate Transaction Reference
    // public function GenerateTransactionReference()
    // {
    //     do {
    //         $transaction_reference = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 15);
    //     } while (TransactionsModel::where('txf', $transaction_reference)->exists());
    //     return $transaction_reference;
    // }
}
