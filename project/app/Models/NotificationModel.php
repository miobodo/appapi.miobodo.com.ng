<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationModel extends Model
{
    
    use HasFactory;

    protected $table = 'notification';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'status',
        'link',
        'img',
        'ref',
    ];

    // Relationship properties
    public function User(){
        return $this->belongsTo(User::class);
    }

}
