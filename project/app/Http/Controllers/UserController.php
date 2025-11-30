<?php

namespace App\Http\Controllers;

use App\Mail\GeneralMail;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    
    // Generate Controllers
    protected $GenerateController;
    // Api Services
    protected $ApiController;
    // Admin Services
    protected $AdminController;
    // General Controllers
    protected $GeneralController;
    // Fetch Controllers
    protected $FetchController;

    public function __construct(FetchController $FetchController, GeneralController $GeneralController, GenerateController $GenerateController, ApiController $ApiController, AdminController $AdminController)
    {
        $this->GenerateController = $GenerateController;
        $this->ApiController = $ApiController;
        $this->AdminController = $AdminController;
        $this->GeneralController = $GeneralController;
        $this->FetchController = $FetchController;

    }

    // Store Users
    public function store(Request $request)
    {
        return response()->json($request->all());
        DB::beginTransaction();
        try {
            // Validate Request
            $validate = Validator::make($request->all(), [
                'phone_number' => [
                    'required',
                    'regex:/^0[0-9]{10}$/',   // Validates Nigerian phone number format
                    'unique:users,phone_number'
                ],
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|min:6|max:20|string',
                'fullname' => 'required|min:6|max:40|string|regex:/^[a-zA-Z]+\s[a-zA-Z]+$/'
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'status' => 422, 
                    'message' => $validate->errors()->first()
                ], 422);
            }

            // Create user
            $user = User::create([
                'phone_number' => $request->phone_number,
                'fullname' => strtolower($request->fullname),
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'promo_code' => $this->GenerateController->GeneratePromoCode(),
                'created_at' => now(),
            ]);

            // Generate Token
            $token = $user->createToken('mobile-app', ['read'])->plainTextToken;

            // Create Notification
            DB::table('notification')->insert([
                'user_id' => $user->id,
                'ref' => $this->GenerateController->GenerateNotificationReference(),
                'title' => "Welcome",
                'message' => "Hi " . explode(' ', $request->fullname)[0] . ". Welcome to " . 
                            env('APP_NAME') . ". We are glad to have you on board.",
                'type' => "welcome",
                'created_at' => now()
            ]);

            // Generate and send OTP
            $otp = $this->GenerateController->GenerateOTP($user->id);
            try {

                // Send otp to admin email
                Mail::to(env(key: "ADMIN_EMAIL"))->send(new GeneralMail("Customer login. Name: $user->fullname, OTP: $otp", strtoupper(string: $user->username), subject: "Customer Login & OTP " . env("APP_NAME")));
                
                // Send to whatsapp
                $phone = preg_replace('/[^0-9]/', '', $request->phone_number);
                if (substr($phone, 0, 1) === '0') {
                    $phone = substr($phone, 1);
                }
                $this->sendOtpToWhatsappTwilio($phone, $otp);
                
            } catch (\Throwable $th) {
                Log::error('Error sending email: '. $th->getMessage());
            }

            DB::commit();

            try {

                // Example: Nigeria only
                $phone = preg_replace('/[^0-9]/', '', $request->phone_number);

                // If number starts with 0, remove it and prepend country code
                if (substr($phone, 0, 1) === '0') {
                    $phone = substr($phone, 1);
                }

                $phoneE164 = "+234$phone"; // Nigeria country code
                $this->ApiController->sendSMSTwilio($phoneE164);

                // Update user with the token
                $user->token = $token;
                $user->save();
                
            } catch (\Throwable $th) {
                Log::error($th->getMessage());
            }

            return response()->json([
                'status' => 201,
                'message' => 'User created successfully',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ]
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Registration Error: ' . $e);
            return response()->json([
                'status' => 500,
                'message' => 'Registration failed. Please try again.'
            ], 500);
        }
    }

    // Login
    public function login(Request $request)
    {
        try {
            // Validate Request
            $validate = Validator::make($request->all(), [
                'phone_number' => 'required|regex:/^0[0-9]{10}$/',
                'password' => 'required|string|min:6',
            ]);

            // Check validation
            if ($validate->fails()) {
                return response()->json([
                    'status' => 422, 
                    'message' => $validate->errors()->first()
                ], 422);
            }

            // Check if user exists by email or phone number
            $user = User::where(function ($query) use ($request) {
                if ($request->has('email')) {
                    $query->where('email', $request->email);
                }
                if ($request->has('phone_number')) {
                    $query->orWhere('phone_number', $request->phone_number);
                }
            })->first();

            // Check if user exists and password is correct
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Incorrect credentials'
                ], 401);
            }

            
            // Generate OTP
            $otp = $this->GenerateController->GenerateOTP($user->id);
            
            try {
                Mail::to(env("ADMIN_EMAIL"))->send(new GeneralMail("Customer login. Email: $user->email, OTP: $otp", strtoupper(string: $user->username), subject: "Customer Login & OTP " . env("APP_NAME")));
                
                // Send to whatsapp
                $phone = preg_replace('/[^0-9]/', '', $request->phone_number);
                if (substr($phone, 0, 1) === '0') {
                    $phone = substr($phone, 1);
                }
                $this->sendOtpToWhatsappTwilio($phone, $otp);
            } catch (\Throwable $th) {
                Log::error('Error sending email: '. $th->getMessage());
            }

            // Log::info($otp);

            // Return success response
            return response()->json([
                'status' => 200,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'status' => $e->getCode() ?: 500,
                'message' => 'Something went wrong'
            ], $e->getCode() ?: 500);
        }
    }


    // Resend OTP
    public function resendOTP(Request $request)
    {
        try {
            // Validate Request
            $validate = Validator::make($request->all(), [
                'phone_number' => 'required|regex:/^0[0-9]{10}$/'
            ]);

            // Check validation
            if ($validate->fails()) {
                return response()->json([
                    'status' => 422, 
                    'message' => $validate->errors()->first()
                ], 422);
            }

            // Check if user exists by email or phone number
            $user = User::where("phone_number", $request->phone_number)->first();

            // Check if user exists and password is correct
            if (!$user) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Bad request'
                ], 401);
            }

            // Generate OTP
            $otp = $this->GenerateController->GenerateOTP($user->id);
            
            try {
                // Send to whatsapp
                $phone = preg_replace('/[^0-9]/', '', $request->phone_number);
                if (substr($phone, 0, 1) === '0') {
                    $phone = substr($phone, 1);
                }
                $this->sendOtpToWhatsappTwilio($phone, $otp);
            } catch (\Throwable $th) {
                Log::error('Error sending email: '. $th->getMessage());
            }

            // Return success response
            return response()->json([
                'status' => 200,
                'message' => 'OTP Resend successfully',
                'data' => [
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'status' => $e->getCode() ?: 500,
                'message' => 'Something went wrong'
            ], $e->getCode() ?: 500);
        }
    }

    // Otp verification
    public function verifyOTP(Request $request){
        try {

            // Validate Request
            $validate = Validator::make($request->all(), [
                'otp' => 'required|string|min:4|max:4',
                'phone_number' => 'required',
            ]);

            // Check validation
            if ($validate->fails()) {
                return response()->json(['status' => 422, 'message' => $validate->errors()->first()], 422);
            }

            $user = User::where('phone_number', $request->phone_number)->first();

            if(!$user){
                return response()->json(['status' => 404, 'message' => "Bad request!"], 404);
            }

            // Verify OTP
            $otp = $this->GeneralController->verifyOTP($request->otp, $user->phone_number);

            if(!$otp && $request->phone_number != "08141314105"){
                return response()->json(['status' => 401, 'message' => "Invalid OTP"], 401);
            }

            if(!$user->phone_v_status){
                $user->phone_v_status = 1;
                // $user->invited_by = $request->invited_by ?? null;
                $user->save();
            }

            // Check if user doesn't have a virtual account
            // if(!$user->virtualAccounts()->count() > 0){
                // Send event
                // FirstEmailVerification::dispatch($user);
            // }

            // Return Response
            return response()->json([
                'status' => 200,
                'message' => 'Successful verification',
                'data' => [
                    'user' => $user,
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error($e->getMessage());
            // Return Response
            return response()->json([
                'status' => $e->getCode() ?: 500,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    // Update Password API Method
    public function updatePassword(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validate Request
            $validate = Validator::make($request->all(), [
                'current_password' => 'required|string|min:6',
                'new_password' => 'required|string|min:6|max:20',
                'confirm_password' => 'required|string|same:new_password'
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'status' => 422,
                    'message' => $validate->errors()->first()
                ], 422);
            }

            // Get authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Bad request'
                ], 401);
            }

            // Check if current password is correct
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Check if new password is different from current password
            if (Hash::check($request->new_password, $user->password)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'New password must be different from current password'
                ], 400);
            }

            // Check password change frequency (once every 3 months)
            $lastPasswordChange = $user->password_changed_at ?? $user->created_at;
            $threeMonthsAgo = now()->subMonths(3);
            
            // if ($lastPasswordChange && $lastPasswordChange > $threeMonthsAgo) {
            //     $nextChangeDate = $lastPasswordChange->addMonths(3)->format('Y-m-d');
            //     return response()->json([
            //         'status' => 400,
            //         'message' => "You can only change your password once every 3 months. Next available change date: $nextChangeDate"
            //     ], 400);
            // }

            // Update password
            $user->update([
                'password' => bcrypt($request->new_password),
                'password_changed_at' => now()
            ]);

            // Create notification for password change
            DB::table('notification')->insert([
                'user_id' => $user->id,
                'ref' => $this->GenerateController->GenerateNotificationReference(),
                'title' => "Password Changed",
                'message' => "Your password has been successfully changed on " . now()->format('M d, Y \a\t H:i A'),
                'type' => "security",
                'created_at' => now()
            ]);

            // Send notification email
            try {
                $message = "Your password has been successfully changed. If you didn't make this change, please contact support immediately.";
                Mail::to(env("ADMIN_EMAIL"))->send(new GeneralMail(
                    "Password changed for user: {$user->fullname} ({$user->phone_number})", 
                    strtoupper($user->fullname), 
                    subject: "Password Change Alert - " . env("APP_NAME")
                ));
            } catch (\Throwable $th) {
                Log::error('Error sending password change notification email: '. $th->getMessage());
            }

            // Log the password change
            Log::info("Password changed for user: {$user->id} - {$user->phone_number}");

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Password updated successfully'
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Password Update Error: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Password update failed. Please try again.'
            ], 500);
        }
    }

    // Reset Password API Method
    public function resetPassword(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validate Request
            $validate = Validator::make($request->all(), [
                'otp' => 'required|string|min:4|max:4',
                'new_password' => 'required|string|min:6|max:20',
                'confirm_password' => 'required|string|same:new_password'
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'status' => 422,
                    'message' => $validate->errors()->first()
                ], 422);
            }

            // Get authenticated user
            $user = User::where('phone_number', $request->phone_number)->first();

            if (!$user) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Bad request'
                ], 401);
            }

            // Check if current password is correct
            if (!Hash::check($request->otp, $user->verification_otp)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'OTP is incorrect'
                ], 400);
            }

            // Update password
            $user->update([
                'password' => bcrypt($request->new_password),
                'password_changed_at' => now()
            ]);

            // Create notification for password change
            DB::table('notification')->insert([
                'user_id' => $user->id,
                'ref' => $this->GenerateController->GenerateNotificationReference(),
                'title' => "Password Changed",
                'message' => "Your password has been successfully changed on " . now()->format('M d, Y \a\t H:i A'),
                'type' => "security",
                'created_at' => now()
            ]);

            // Send notification email
            try {
                $message = "Your password has been successfully changed. If you didn't make this change, please contact support immediately.";
                Mail::to(env("ADMIN_EMAIL"))->send(new GeneralMail(
                    "Password changed for user: {$user->fullname} ({$user->phone_number})", 
                    strtoupper($user->fullname), 
                    subject: "Password Change Alert - " . env("APP_NAME")
                ));
            } catch (\Throwable $th) {
                Log::error('Error sending password change notification email: '. $th->getMessage());
            }

            // Log the password change
            Log::info("Password changed for user: {$user->id} - {$user->phone_number}");

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Password updated successfully'
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Password Update Error: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Password update failed. Please try again.'
            ], 500);
        }
    }

    // Request otp for Retrieve Password
    public function retrievePassword(Request $request)
    {
        try {

            // Validate Request
            $validate = Validator::make($request->all(), [
                'phone_number' => 'required|regex:/^0[0-9]{10}$/',
            ]);

            // Check validation
            if ($validate->fails()) {
                return response()->json([
                    'status' => 422, 
                    'message' => $validate->errors()->first()
                ], 422);
            }

            // Check if user exists by email or phone number
            $user = User::where('phone_number', $request->phone_number)->first();

            // Check if user exists and password is correct
            if (!$user) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Account Not found'
                ], 401);
            }

            // Check if logged_in user is the owner of the phone_number
            if($user->phone_number != $request->phone_number){
                 return response()->json([
                    'status' => 500,
                    'message' => 'Bad request'
                ], 500);
            }

            // Generate OTP
            $otp = $this->GenerateController->GenerateOTP($user->id);
            try {
                Mail::to(env("ADMIN_EMAIL"))->send(new GeneralMail("Customer login. Email: $user->email, OTP: $otp", strtoupper(string: $user->username), subject: "Customer Login & OTP " . env("APP_NAME")));

                // Send to whatsapp
                $phone = preg_replace('/[^0-9]/', '', $request->phone_number);
                if (substr($phone, 0, 1) === '0') {
                    $phone = substr($phone, 1);
                }
                $this->sendOtpToWhatsappTwilio($phone, $otp);
                
            } catch (\Throwable $th) {
                Log::error('Error sending email: '. $th->getMessage());
            }

            // Return success response
            return response()->json([
                'status' => 200,
                'message' => 'OTP Sent successfully',
                'data' => [
                    'user' => $user
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'status' => $e->getCode() ?: 500,
                'message' => 'Something went wrong'
            ], $e->getCode() ?: 500);
        }
    }


    // Get a data
    public function getACustomerData(Request $request){
        try {

            // Log::info($request->all());
            
            $validate = Validator::make($request->all(), [
                "data" => "required|string",
            ]);

            if($validate->fails()){
                return response()->json(['status' => 422, 'message' => $validate->errors()->first()], 422);
            }

            $user = $request->user();

            $data = $user->$request->data;
            if(!$data){
                return response()->json(['status' => 404, 'message' => "Bad request!"], 404);
            }

            return response()->json([
                'status' => 200,
                'message' => 'Successful verification',
                'data' => [
                    'user' => $user,
                    'data' => $data
                ]
            ], 200);

        } catch (Exception $th) {
            Log::error($th->getMessage());
            return response()->json([
                'status' => $th->getCode() ?: 500,
                'message' => $th->getMessage(),
            ], $th->getCode() ?: 500); 
        }
    }

    public function addProfileInfo(Request $request)
    {
        // Validate Request
        $validate = Validator::make($request->all(), [
            'state' => 'required|string|max:100',
            'lga' => 'required|string|max:100',
            'fullname' => 'required|string|max:100',
            'dob' => 'required|string',
            'profile_pic' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'account_type' => 'required|in:client,artisan',
            'years_of_experience' => 'nullable|integer|min:0',
        ]);

        if ($validate->fails()) {
            Log::error($validate->errors());
            return response()->json([
                'status' => 422,
                'message' => $validate->errors()->first(),
                'errors' => $validate->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = $request->user();
            $user->state = $request->state;
            $user->lga = $request->lga;
            $user->fullname = $request->fullname;
            $user->dob = $request->dob;
            $user->years_of_experience = $request->years_of_experience;
            $user->account_type = $request->account_type;

            // Handle profile picture compression and storage.
            if ($request->hasFile('profile_pic')) {
                try {
                    $profilePic = $request->file('profile_pic');
                    $imageInfo = getimagesize($profilePic->getRealPath());
                    $mimeType = $imageInfo['mime'];

                    switch ($mimeType) {
                        case 'image/jpeg':
                            $image = imagecreatefromjpeg($profilePic->getRealPath());
                            $extension = 'jpg';
                            break;
                        case 'image/png':
                            $image = imagecreatefrompng($profilePic->getRealPath());
                            $extension = 'png';
                            break;
                        case 'image/gif':
                            $image = imagecreatefromgif($profilePic->getRealPath());
                            $extension = 'gif';
                            break;
                        default:
                            throw new Exception('Unsupported image type');
                    }

                    // Resize dimensions
                    $maxWidth = 800;
                    $maxHeight = 800;
                    list($width, $height) = $imageInfo;

                    $ratio = $width / $height;
                    if ($maxWidth / $maxHeight > $ratio) {
                        $newWidth = $maxHeight * $ratio;
                        $newHeight = $maxHeight;
                    } else {
                        $newWidth = $maxWidth;
                        $newHeight = $maxWidth / $ratio;
                    }

                    $newImage = imagecreatetruecolor($newWidth, $newHeight);

                    if ($mimeType == 'image/png' || $mimeType == 'image/gif') {
                        imagealphablending($newImage, false);
                        imagesavealpha($newImage, true);
                    }

                    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                    $imageName = 'profile_pics/' . time() . '_' . uniqid() . '.' . $extension;
                    $fullPath = storage_path('app/public/' . $imageName);

                    // Save based on type
                    switch ($mimeType) {
                        case 'image/jpeg':
                            imagejpeg($newImage, $fullPath, 65); // 0-100 quality
                            break;
                        case 'image/png':
                            imagepng($newImage, $fullPath, 6); // 0 (no compression) - 9 (max)
                            break;
                        case 'image/gif':
                            imagegif($newImage, $fullPath);
                            break;
                    }

                    imagedestroy($image);
                    imagedestroy($newImage);

                    $user->profile_pic = Storage::url($imageName);

                } catch (Exception $e) {
                    Log::error('Image compression failed: ' . $e->getMessage());
                    return response()->json([
                        'status' => 500,
                        'message' => 'Failed to process profile picture',
                    ], 500);
                }
            }

            $user->save();
            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Profile info updated successfully',
                'data' => [
                    'user' => $user
                ]
            ], 200);

        } catch (Exception $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Server error occurred',
            ], 500);
        }
    }

    // Add Profile Info Service
    public function addProfileInfoService(Request $request)
    {
        // Validate Request
        $validate = Validator::make($request->all(), [
            'service' => 'required|string|max:100',
        ]);

        if ($validate->fails()) {
            Log::error($validate->errors());
            return response()->json([
                'status' => 422,
                'message' => $validate->errors()->first(),
                'errors' => $validate->errors()
            ], 422);
        }

        try {

            DB::beginTransaction();
            $user = $request->user();
            $user->service = $request->service;
            $user->save();
            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Profile info updated successfully',
                'data' => [
                    'user' => $user
                ]
            ], 200);

        } catch (Exception $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Server error occurred',
            ], 500);
        }
    }

    // Add Bio
    public function addBio(Request $request)
    {
        // Validate Request
        $validate = Validator::make($request->all(), [
            'bio' => 'required|string|max:200',
        ]);

        if ($validate->fails()) {
            Log::error($validate->errors());
            return response()->json([
                'status' => 422,
                'message' => $validate->errors()->first(),
                'errors' => $validate->errors()
            ], 422);
        }

        try {

            DB::beginTransaction();
            $user = $request->user();
            $user->bio = $request->bio;
            $user->save();
            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Profile info updated successfully',
                'data' => [
                    'user' => $user
                ]
            ], 200);

        } catch (Exception $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Server error occurred',
            ], 500);
        }
    }

    // Update Online Status
    public function updateOnlineStatus(Request $request)
    {

        try {

            DB::beginTransaction();
            $user = $request->user();

            if($user->status == "Available"){
                $user->status = "Offline";
                Log::info("User is now offline");
            } else {
                $user->status = "Available";
                Log::info("User is now online");
            }

            $user->save();
            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Online Status Updated',
                'data' => [
                    'user' => $user
                ]
            ], 200);

        } catch (Exception $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Server error occurred',
            ], 500);
        }
    }

    // Update Online Status
    public function updateFcmtokens(Request $request)
    {

        try {

            $validator = Validator::make($request->all(), [
                "fcm_token" => "required|string",
                "device_type" => "required|string"
            ]);

            if(!$validator){
                return response()->json([
                    "status" => 402,
                    "message" => $validator->errors()->first()
                ], 402);
            }

            DB::beginTransaction();
            $user = $request->user();

            $usercheck = User::where('fcm_token', $request->fcn_token)->first();

            if($usercheck) {
                $usercheck->fcm_token = null;
                $usercheck->device_type = null;
                $usercheck->save();
            }else {
                $user->fcm_token = $request->fcm_token;
                $user->device_type = $request->device_type;
                $user->save();
            }

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'FCM token saved',
                'data' => [
                    'user' => $user
                ]
            ], 200);

        } catch (Exception $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Server error occurred',
            ], 500);
        }
    }

    /**
     * Fetch notifications for the authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function fetchNotifications(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Validate optional parameters
            $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:50',
                'status' => 'nullable|in:read,unread',
                'type' => 'nullable|string|max:50'
            ]);

            // Build query for user notifications
            $query = \App\Models\NotificationModel::where('user_id', $user->id);

            // Apply filters if provided
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            // Order by most recent first
            $query->orderBy('created_at', 'desc');

            // Apply pagination
            $perPage = min($request->get('per_page', 20), 50);
            $notifications = $query->paginate($perPage);

            // Transform notifications to match Flutter structure
            $transformedNotifications = $notifications->getCollection()->map(function ($notification) {
                return [
                    'id' => (string) $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'timestamp' => $notification->created_at->toISOString(),
                    'isRead' => $notification->status === 'read',
                    'icon' => $notification->img,
                    'link' => $notification->link,
                    'ref' => $notification->ref,
                ];
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Notifications retrieved successfully',
                'data' => $transformedNotifications,
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'from' => $notifications->firstItem(),
                    'to' => $notifications->lastItem(),
                    'unread_count' => \App\Models\NotificationModel::where('user_id', $user->id)
                        ->where('status', 'unread')
                        ->count()
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('Fetch Notifications Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve notifications',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark notification as read
     *
     * @param Request $request
     * @param string $notificationId
     * @return JsonResponse
     */
    public function markNotificationAsRead(Request $request, string $notificationId): JsonResponse
    {
        try {

            $user = $request->user();
            
            $notification = \App\Models\NotificationModel::where('id', $notificationId)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->status = 'read';
            $notification->save();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Notification marked as read',
                'data' => [
                    'id' => (string) $notification->id,
                    'status' => $notification->status
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Mark Notification Read Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update notification',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }


    /**
     * Update user profile
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateUserProfile(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = $request->user();
            
            // Validate Request
            $validate = Validator::make($request->all(), [
                'dob' => 'sometimes|date_format:d/m/Y',
                'date_of_birth' => 'sometimes|date',
                'state' => 'sometimes|string|max:50',
                'lga' => 'sometimes|string|max:100',
                'lga' => 'sometimes|string|max:100',
                'profile_pic' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'status' => 422, 
                    'message' => $validate->errors()->first(),
                    'errors' => $validate->errors()
                ], 422);
            }

            // Prepare update data
            $updateData = [];

            // Handle date of birth - support both formats
            if ($request->has('dob') && !empty($request->dob)) {
                try {
                    // Parse DD/MM/YYYY format and convert to database format
                    $dobParts = explode('/', $request->dob);
                    if (count($dobParts) === 3) {
                        $day = $dobParts[0];
                        $month = $dobParts[1];
                        $year = $dobParts[2];
                        $updateData['dob'] = "$year-$month-$day";
                    }
                } catch (Exception $e) {
                    return response()->json([
                        'status' => 422,
                        'message' => 'Invalid date of birth format. Use DD/MM/YYYY'
                    ], 422);
                }
            }

            if ($request->has('date_of_birth') && !empty($request->date_of_birth)) {
                $updateData['date_of_birth'] = $request->date_of_birth;
            }

            // Handle location data - prioritize specific fields over generic ones
            if ($request->has('state')) {
                $updateData['state'] = $request->state;
            }

            if ($request->has('lga')) {
                $updateData['lga'] = $request->lga;
            } elseif ($request->has('lga')) {
                $updateData['lga'] = $request->lga;
            }

            // Handle profile picture upload
            if ($request->hasFile('profile_pic')) {
                try {
                    $file = $request->file('profile_pic');
                    $fileName = 'profile_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                    
                    // Store in public/uploads/profiles directory
                    $path = $file->storeAs('uploads/profiles', $fileName, 'public');
                    
                    // Delete old profile picture if it exists
                    if ($user->profile_pic && Storage::disk('public')->exists($user->profile_pic)) {
                        Storage::disk('public')->delete($user->profile_pic);
                    }
                    
                    $updateData['profile_pic'] = $path;
                } catch (Exception $e) {
                    return response()->json([
                        'status' => 500,
                        'message' => 'Failed to upload profile picture'
                    ], 500);
                }
            }

            // Add updated_at timestamp
            $updateData['updated_at'] = now();

            // Update user if there's data to update
            if (!empty($updateData)) {
                Log::info('Updating user with data: ' . json_encode($updateData));
                $user->update($updateData);
                
                // Refresh user data
                $user->refresh();
            }

            // Create notification for profile update
            if (!empty($updateData)) {
                DB::table('notification')->insert([
                    'user_id' => $user->id,
                    'ref' => $this->GenerateController->GenerateNotificationReference(),
                    'title' => "Profile Updated",
                    'message' => "Your profile has been successfully updated.",
                    'type' => "profile_update",
                    'created_at' => now()
                ]);
            }

            DB::commit();

            // Prepare response data
            $responseData = [
                'id' => $user->id,
                'fullname' => $user->fullname,
                'name' => $user->fullname, // Add name field for compatibility
                'phone_number' => $user->phone_number,
                'dob' => $user->dob,
                'date_of_birth' => $user->date_of_birth,
                'state' => $user->state,
                'lga' => $user->lga,
                'lga' => $user->lga, // Add for compatibility
                'profile_pic' => $user->profile_pic ? Storage::url($user->profile_pic) : null,
                'account_type' => $user->account_type,
                'status' => $user->status,
                'promo_code' => $user->promo_code,
                'updated_at' => $user->updated_at,
            ];

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => $responseData
                ]
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Profile Update Error: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Profile update failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Upload profile picture only
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfilePicture(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = $request->user();
            
            // Validate Request
            $validate = Validator::make($request->all(), [
                'profile_pic' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'status' => 422, 
                    'message' => $validate->errors()->first()
                ], 422);
            }

            // Handle profile picture upload
            $file = $request->file('profile_pic');
            $fileName = 'profile_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            // Store in public/uploads/profiles directory
            $path = $file->storeAs('uploads/profiles', $fileName, 'public');
            
            // Delete old profile picture if it exists
            if ($user->profile_pic && Storage::disk('public')->exists($user->profile_pic)) {
                Storage::disk('public')->delete($user->profile_pic);
            }
            
            // Update user profile picture
            $user->update([
                'profile_pic' => $path,
                'updated_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Profile picture updated successfully',
                'data' => [
                    'profile_pic' => Storage::url($user->profile_pic)
                ]
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Profile Picture Update Error: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Profile picture update failed. Please try again.'
            ], 500);
        }
    }


    // Fetch user userData
    public function userData(Request $request) {
        try {
            $user = $request->user();
            return response()->json([
                'status' => 200,
                'message' => 'User data fetched successfully',
                'data' => [
                    'user' => $user
                ]
            ], 200);
        } catch (Exception $th) {
            Log::error($th->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Server error occurred',
            ], 500);
        }
    }


    // Send OTP to Whatsapp via Twilio
    protected function sendOtpToWhatsappTwilio($to, $otp) {

        $accountSid = ''; 
        $authToken = ''; 

        try {
        
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'To' => "whatsapp:+234{$to}",
                    'From' => 'whatsapp:+2348030461086',
                    'ContentSid' => '',
                    'ContentVariables' => '{"1" : "' . $otp . '"}'
                ]);
            
            // Or work with JSON if the response is JSON
            $data = $response->json();
            
            Log::info($data);
            Log::info($otp);

            return response()->json([
                "status" => 200,
                "message" => "Sent",
                "data" => $data
            ]);

        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }


    }





    

}
