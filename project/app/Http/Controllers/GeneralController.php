<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class GeneralController extends Controller
{
    // Promo Code
    public function CheckPromoCodeDuringRegistration($code){
        // Check if the code is valid
        switch ($code) {
            case 'paya':
                return false;
            default:
                $user = User::where('promo_code', strtolower($code))->first();
                if ($user) {
                    return true;
                } else {
                    return false;
                }
        }

    }

    // Validate OTP
    public function verifyOTP($otp, $phone_number){

        $user = User::where('phone_number', $phone_number)->first();
        if ($user && password_verify($otp, $user->verification_otp)) {
            return true;
        } else {
            return false;
        }

    }
}
