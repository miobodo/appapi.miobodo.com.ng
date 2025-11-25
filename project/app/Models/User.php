<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'fullname',
        'username',
        'gender',
        'email',
        'profile_pic',
        'phone_number',
        'verification_otp',
        'otp_created_at',
        'email_v_status',
        'password',
        'bvn',
        'bvn_v_status',
        'balance',
        'income',
        'expenses',
        'device_id',
        'is_online',
        'last_seen_at',
        'pin',
        'token',
        'receive_transaction_emails',
        'receive_push_notifications',
        'weekly_newsletters',
        'account_type',
        'dob',
        'years_of_experience',
        'service',
        'bio',
        'location',
        'tier',
        'promo_code',
        'fcm_token',
        'device_type',
        'invited_by',
        'rating',
        'service_icon',
        'service_icon_bg',
        'status',
        'state',
        'lga',
        'verified',
        'experience'
    ];

    protected $appends = ['portfolio', 'profileid', 'phonenumber', 'serviceIcon', 'serviceIconbg'];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_otp',
        'otp_created_at',
        'device_id',
        'pin',
    ];

    protected $casts = [
        'email_v_status' => 'boolean',
        'bvn_v_status' => 'boolean',
        'receive_transaction_emails' => 'boolean',
        'receive_push_notifications' => 'boolean',
        'weekly_newsletters' => 'boolean',
        'rating' => 'float',
        'is_online' => 'boolean',
        'verified' => 'boolean'
    ];

    public function portfolioProjects()
    {
        return $this->hasMany(PortfolioProject::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function invitees()
    {
        return $this->hasMany(User::class, 'invited_by');
    }

    public function getFullnameAttribute($value)
    {
        return ucwords($value);
    }


    public function setServiceAttribute($value)
    {
        $this->attributes['service'] = strtolower($value);
    }

    // Accessor for Flutter compatibility - returns portfolio projects
    public function getPortfolioAttribute()
    {
        return $this->portfolioProjects()->get()->map(function ($project) {
            return [
                'title' => $project->title,
                'portfolio_bg' => $project->portfolio_bg,
                'portfolio_id' => $project->portfolio_id,
                'role' => $project->role,
                'project_description' => $project->project_description,
                'project_images' => $project->project_images ?? []
            ];
        })->toArray();
    }

    // Accessor for Flutter compatibility - returns string ID
    public function getProfileidAttribute()
    {
        return (string) $this->id;
    }

    // Accessor for Flutter compatibility - returns phone_number as phonenumber
    public function getPhoneNumberAttribute($value)
    {
        return $value ?? '';
    }

    public function getNameAttribute($value)
    {
        return $value ?? '';
    }

    public function getProfilePicAttribute($value)
    {
        // If the value is null or empty, return a default profile picture
        if (empty($value)) {
            // Return a default Unsplash profile picture
            return "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=300&h=300&fit=crop&crop=face";
        }

        // If it's already a full URL, return it as is
        if (str_starts_with($value, 'http')) {
            return $value;
        }

        // Handle Storage paths
        $baseUrl = rtrim(env('APP_URL'), '/').'/project/storage/app/public';
        $cleanedPath = ltrim(preg_replace('#^/?(storage/)?#', '', $value), '/');
        return "$baseUrl/$cleanedPath";
    }

    // Method to get service icon based on service type
    public function getServiceIconPath()
    {
        $serviceIcons = [
            'painter'      => 'images/map_painter.png',
            'plumber'      => 'images/ic_twotone-plumbing.png',
            'caterer'      => 'images/chef-cap.png',
            'electrician'  => 'images/heroicons-solid_light-bulb.png',
            'cleaner'      => 'images/arcticons_cache-cleaner.png',
            'auto mec.'    => 'images/car-sport.png',
            'locksmith'    => 'images/map_locksmith.png',
            'tiler'        => 'images/game-icons_domino-tiles.png',
            'babysitter'   => 'images/fa-solid_baby.png',
            'gardener'     => 'images/entypo_flower.png',
            'carpenter'    => 'images/hammer.png',
            'barber'       => 'images/hair-clipper.png',
            'bricklayer'   => 'images/brick-pile.png',
            'uphosterer'   => 'images/furniture.png',
            'lawyer'       => 'images/gavel.png',
            'welder'       => 'images/welder-industrial-factory.png',
            'pedicurist'   => 'images/fingernail.png',
            'hairdresser'  => 'images/hair-dryer.png',
        ];

        return $serviceIcons[strtolower($this->service)] ?? 'images/map_painter.png';
    }


    public function getServiceIconAttribute ()
    {
        $serviceIcons = [
            'painter'      => 'images/map_painter.png',
            'plumber'      => 'images/ic_twotone-plumbing.png',
            'caterer'      => 'images/chef-cap.png',
            'electrician'  => 'images/heroicons-solid_light-bulb.png',
            'cleaner'      => 'images/arcticons_cache-cleaner.png',
            'auto mec.'    => 'images/car-sport.png',
            'locksmith'    => 'images/map_locksmith.png',
            'tiler'        => 'images/game-icons_domino-tiles.png',
            'babysitter'   => 'images/fa-solid_baby.png',
            'gardener'     => 'images/entypo_flower.png',
            'carpenter'    => 'images/hammer.png',
            'barber'       => 'images/hair-clipper.png',
            'bricklayer'   => 'images/brick-pile.png',
            'uphosterer'   => 'images/furniture.png',
            'lawyer'       => 'images/gavel.png',
            'welder'       => 'images/welder-industrial-factory.png',
            'pedicurist'   => 'images/fingernail.png',
            'hairdresser'  => 'images/hair-dryer.png',
        ];

        return $serviceIcons[strtolower($this->service)] ?? 'images/map_painter.png';
    }

    // Method to get service icon background color
    public function getServiceIconBgColor()
    {
        $serviceColors = [
            'painter'      => '#fee1d5',
            'plumber'      => '#d0e8f5',
            'caterer'      => '#d2e6e4',
            'electrician'  => '#fff3d9',
            'cleaner'      => '#d2ecd3',
            'auto mec.'    => '#d1d7e7',
            'locksmith'    => '#ffe9e9',
            'tiler'        => '#d6d6d6',
            'babysitter'   => '#e9edf4',
            'gardener'     => '#d2ecd3',
            'carpenter'    => '#e9edf5',
            'barber'       => '#f0eded',
            'bricklayer'   => '#d1d7e7',
            'uphosterer'   => '#fff3d9',
            'lawyer'       => '#d2ecd3',
            'welder'       => '#d2e6e4',
            'pedicurist'   => '#ffe1d5',
            'hairdresser'  => '#d0e8f5',
        ];

        return $serviceColors[strtolower($this->service)] ?? '#f0f0f0';
    }

    // Method to get service icon background color
    public function getServiceIconBgAttribute()
    {
        $serviceColors = [
            'painter'      => '#fee1d5',
            'plumber'      => '#d0e8f5',
            'caterer'      => '#d2e6e4',
            'electrician'  => '#fff3d9',
            'cleaner'      => '#d2ecd3',
            'auto mec.'    => '#d1d7e7',
            'locksmith'    => '#ffe9e9',
            'tiler'        => '#d6d6d6',
            'babysitter'   => '#e9edf4',
            'gardener'     => '#d2ecd3',
            'carpenter'    => '#e9edf5',
            'barber'       => '#f0eded',
            'bricklayer'   => '#d1d7e7',
            'uphosterer'   => '#fff3d9',
            'lawyer'       => '#d2ecd3',
            'welder'       => '#d2e6e4',
            'pedicurist'   => '#ffe1d5',
            'hairdresser'  => '#d0e8f5',
        ];

        return $serviceColors[strtolower($this->service)] ?? '#f0f0f0';
    }

}