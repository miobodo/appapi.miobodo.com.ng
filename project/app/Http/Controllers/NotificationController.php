<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    

    protected $GenerateController;

    public function __construct(GenerateController $GenerateController)
    {
        $this->GenerateController = $GenerateController;
    }
    
    // Send Push Notification
    public function sendPushNotification(Request $request)
    {

        try {
            
            $validate = Validator::make($request->all(), [
                'receiver_id' => 'required|string',
                'message' => 'required|string',
                'title' => 'required|string',
                'type' => 'required|string'
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation Error',
                    'errors' => $validate->errors()
                ], 422);
            }

            // Create Notification
            DB::table('notification')->insert([
                'user_id' => $request->receiver_id,
                'ref' => $this->GenerateController->GenerateNotificationReference(),
                'title' => $request->title,
                'message' => $request->message,
                'type' => $request->type,
                'created_at' => now()
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Push Notification Sent Successfully'
            ], 200);


        } catch (\Throwable $th) {
            Log::error('Error in sendPushNotification: ' . $th->getMessage());
            return response()->json([
                'status' => 200,
                'message' => 'An error occurred while sending push notification'
            ], 500);
        }

    }


    public function updatePushNotifications(Request $request)
    {
        try {

            DB::beginTransaction();
            $user = $request->user();


            if($user->receive_push_notifications == 1) {
                $user->receive_push_notifications = 0;
            }else {
                $user->receive_push_notifications = 1;
            }

            $user->save();

            DB::commit();

            Log::info("User push notification setting updated to: " . ($request->receive_push_notifications ? 'enabled' : 'disabled'));

            return response()->json([
                'status' => 200,
                'message' => 'Push notification setting updated successfully',
                'data' => [
                    'user' => $user,
                ]
            ], 200);

        } catch (Exception $th) {
            DB::rollBack();
            Log::error('Push notification update error: ' . $th->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Server error occurred while updating push notification setting',
            ], 500);
        }
    }

    // Update Email Notification Status
    public function updateEmailNotifications(Request $request)
    {
        try {
            
            DB::beginTransaction();
            $user = $request->user();

            if($user->receive_transaction_emails == 1) {
                $user->receive_transaction_emails = 0;
            }else {
                $user->receive_transaction_emails = 1;
            }

            $user->save();

            DB::commit();

            Log::info("User email notification setting updated to: " . ($request->receive_transaction_emails ? 'enabled' : 'disabled'));

            return response()->json([
                'status' => 200,
                'message' => 'Email notification setting updated successfully',
                'data' => [
                    'user' => $user,
                    'receive_transaction_emails' => $user->receive_transaction_emails
                ]
            ], 200);

        } catch (Exception $th) {
            DB::rollBack();
            Log::error('Email notification update error: ' . $th->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Server error occurred while updating email notification setting',
            ], 500);
        }
    }

    // Get Current Notification Settings
    public function getNotificationSettings(Request $request)
    {
        try {
            $user = $request->user();

            return response()->json([
                'status' => 200,
                'message' => 'Notification settings retrieved successfully',
                'data' => [
                    'receive_push_notifications' => $user->receive_push_notifications,
                    'receive_transaction_emails' => $user->receive_transaction_emails
                ]
            ], 200);

        } catch (Exception $th) {
            Log::error('Get notification settings error: ' . $th->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Server error occurred while retrieving notification settings',
            ], 500);
        }
    }


}
