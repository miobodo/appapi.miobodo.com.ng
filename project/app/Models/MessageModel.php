<?php
// Message Model (app/Models/Message.php)
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageModel extends Model
{
    protected $fillable = [
        'chat_id',
        'sender_id',
        'content',
        'is_read',
        'message_type'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(ChatModel::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}