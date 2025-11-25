<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiController extends Controller
{
    
    public function SendChampCall($to, $message)
    {
        try {
            $Send = Http::withHeaders([
                "Authorization" => "Bearer ".env("SENDCHAMP_KEY"),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://api.sendchamp.com/api/v1/sms/send', [
                "to"  => $to,
                "message"=> $message,
                "sender_name"=> env("SENDCHAP_USERNAME"),
                "route"=> "dnd"
            ]);

            $SendJson = $Send->json();
            Log::info($SendJson);

            if ($SendJson['status'] != "success") {
                return [
                    "status" => false,
                    "message" => $SendJson['message']
                ];
            } else {
                return [
                    "status" => true,
                    "id" => $SendJson['data']['id']
                ];
            }
        } catch (Exception $th) {
            return [
                "status" => false,
                "error" => $th->getMessage()
            ];
        }
    }

    // Twilio SMS (OTP)
    public function sendSMSTwilio($phone)
    {
        $accountSid = env("TWILIO_SID");
        $authToken  = env("TWILIO_KEY");
        $serviceSid = env("TWILIO_SERVICE_SID");

        $url = "https://verify.twilio.com/v2/Services/{$serviceSid}/Verifications";

        $request = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->post($url, [
                'To'      => env("TWILIO_DEMO_PHONE"),
                'Channel' => 'sms'
            ]);

        return $request->json();
    }


}
