<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortfolioProject extends Model
{
    use HasFactory;

    protected $table = 'portfolio_project';

    protected $fillable = [
        'user_id',
        'title',
        'portfolio_bg',
        'portfolio_id',
        'role',
        'project_description',
        'project_images',
    ];

    protected $casts = [
        'project_images' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Automatically generate portfolio_id if not provided
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->portfolio_id) {
                $model->portfolio_id = 'p' . $model->user_id . substr(uniqid(), -3);
            }
        });
    }

    // Accessor to ensure project_images is always an array
    public function getProjectImagesAttribute($value)
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }
        return $value ?? [];
    }
}