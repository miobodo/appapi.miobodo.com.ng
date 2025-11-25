<?php


use App\Http\Controllers\Api\ArtisanController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PortfoliProjectController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

// User
Route::group(['prefix' => 'user', 'namespace' => 'App\Http\Controllers'], function () {
    // User Registration
    Route::post('/', [UserController::class, "store"]);
    Route::get('/', [UserController::class, "getACustomerData"]);
    Route::post('/login', [UserController::class, "login"]);
    Route::post('/pin', [UserController::class, "createPin"]);
    Route::get('/pin', [UserController::class, "verifyPin"]);
});

// Fetch
Route::middleware(['auth:sanctum'])->prefix('fetch')->namespace('App\Http\Controllers')->group(function () {
    Route::get('/user', [UserController::class, "userData"]);
    Route::get('/notification', [UserController::class, 'fetchNotifications']);
    Route::patch('/notification/{id}/read', [UserController::class, 'markNotificationAsRead']);
});

// notification
Route::middleware(['auth:sanctum'])->prefix('notification')->namespace('App\Http\Controllers')->group(function () {
    Route::post('/push_notification', [NotificationController::class, 'sendPushNotification']);
});

// Verify
Route::group(['prefix' => 'verify', 'namespace' => 'App\Http\Controllers'], function () {
    Route::post('/otp', [UserController::class, "verifyOTP"]);
});

// Update
Route::middleware(['auth:sanctum'])->prefix('update')->namespace('App\Http\Controllers')->group(function () {
    Route::post('/add_profile_info', [UserController::class, "addProfileInfo"]);
    Route::post('/add_artisan_bio', [UserController::class, "addBio"]);
    Route::post('/add_artisan_service', [UserController::class, "addProfileInfoService"]);
    Route::post('/user_profile_update', [UserController::class, 'updateUserProfile']);
    Route::post('/profile_picture', [UserController::class, 'updateProfilePicture']);
    Route::post('/status', [UserController::class, 'updateOnlineStatus']);
    Route::post('/fcm-token', [UserController::class, 'updateFcmtokens']);
    Route::post('/password', [UserController::class, 'updatePassword']);
    Route::post('/portfolio', [PortfoliProjectController::class, 'updatePortfolio']);
    Route::post('/notification/push', [NotificationController::class, 'updatePushNotifications']);
    Route::post('/notification/email', [NotificationController::class, 'updateEmailNotifications']);
});


Route::middleware(['auth:sanctum'])->prefix('delete')->namespace('App\Http\Controllers')->group(function () {
    Route::post('/portfolio', [PortfoliProjectController::class, 'deletePortfolio']);
});


Route::middleware([])->prefix('retrieve')->namespace('App\Http\Controllers')->group(function () {
    Route::post('/password/otp', [UserController::class, 'retrievePassword']);
});

Route::middleware([])->prefix('reset')->namespace('App\Http\Controllers')->group(function () {
    Route::post('/password', [UserController::class, 'resetPassword']);
});

Route::middleware([])->prefix('resend')->namespace('App\Http\Controllers')->group(function () {
    Route::post('/otp', [UserController::class, 'resendOTP']);
});


// Portfolio project
Route::middleware(['auth:sanctum'])->prefix('portfolio')->namespace('App\Http\Controllers')->group(function () {
    Route::post('/', [PortfoliProjectController::class, "addProfileInfo"]);
});

// ==============================================
// 3. ROUTES (routes/api.php)
// ==============================================

Route::middleware('auth:sanctum')->group(function () {
    // Chat routes
    Route::get('/chats', [ChatController::class, 'getChats']);
    Route::get('/chats/search', [ChatController::class, 'searchChats']);
    Route::get('/chats/{chatId}/messages', [ChatController::class, 'getMessages']);
    Route::post('/chats/send-message', [ChatController::class, 'sendMessage']);
    
    // User status routes
    Route::get('/users/{userId}/status', [ChatController::class, 'getUserStatus']);
    Route::post('/users/update-status', [ChatController::class, 'updateOnlineStatus']);
});

// Artisan routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Artisan fetching routes
    Route::prefix('fetch/artisan')->group(function () {
        // Fetch all artisans (with location-based priority)
        Route::get('/', [ArtisanController::class, 'fetchAllArtisans']);
        // Fetch verified artisans only (with location-based priority)
        Route::get('/verified', [ArtisanController::class, 'fetchVerifiedArtisans']);
        // Fetch artisans by specific service (with location-based priority)
        Route::get('/{service}', [ArtisanController::class, 'fetchArtisansByService'])
             ->where('service', '[a-zA-Z\s\.]+'); // Allow letters, spaces, and dots for services like "auto mec."
    });
    // Individual artisan profile
    Route::get('/artisan/profile/{profileId}', [ArtisanController::class, 'getArtisanProfile'])
         ->where('profileId', '[0-9]+');
    // Search artisans
    Route::get('/artisan/search', [ArtisanController::class, 'searchArtisans']);
});


Route::get('/test/twilio', [UserController::class, 'sendOtpToWhatsappTwilio']);
