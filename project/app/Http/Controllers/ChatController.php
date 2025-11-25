<?php

// ==============================================
// 1. CHAT CONTROLLER (app/Http/Controllers/ChatController.php)
// ==============================================

namespace App\Http\Controllers;

use App\Models\ChatModel;
use App\Models\MessageModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    /**
     * Get all chats for the authenticated user
     */
    public function getChats()
    {
        $userId = Auth::id();
        
        $chats = ChatModel::where('user1_id', $userId)
                    ->orWhere('user2_id', $userId)
                    ->with(['user1', 'user2', 'lastMessage'])
                    ->get()
                    ->map(function ($chat) use ($userId) {
                        $otherUser = $chat->user1_id === $userId ? $chat->user2 : $chat->user1;
                        $unreadCount = MessageModel::where('chat_id', $chat->id)
                                            ->where('sender_id', '!=', $userId)
                                            ->where('is_read', false)
                                            ->count();
                        
                        return [
                            'id' => $chat->id,
                            'name' => $otherUser->name,
                            'message' => $chat->lastMessage ? $chat->lastMessage->content : 'No messages yet',
                            'time' => $chat->lastMessage ? $chat->lastMessage->created_at->diffForHumans() : 'Never',
                            'avatar' => $otherUser->avatar_url ?? 'https://i.pravatar.cc/150?u=' . $otherUser->id,
                            'unreadCount' => $unreadCount,
                            'isOnline' => $otherUser->is_online ?? false,
                            'other_user_id' => $otherUser->id,
                        ];
                    })
                    ->sortByDesc(function ($chat) {
                        return $chat['time'];
                    })
                    ->values();

        return response()->json([
            'success' => true,
            'chats' => $chats
        ]);
    }

    /**
     * Get messages for a specific chat
     */
    public function getMessages(Request $request, $chatId)
    {
        $userId = Auth::id();
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 50);

        // Verify user is part of this chat
        $chat = ChatModel::where('id', $chatId)
                   ->where(function ($query) use ($userId) {
                       $query->where('user1_id', $userId)
                             ->orWhere('user2_id', $userId);
                   })
                   ->first();

        if (!$chat) {
            return response()->json([
                'success' => false,
                'message' => 'Chat not found or access denied'
            ], 404);
        }

        $messages = MessageModel::where('chat_id', $chatId)
                          ->with('sender:id,name,avatar_url')
                          ->orderBy('created_at', 'desc')
                          ->paginate($limit, ['*'], 'page', $page);

        $formattedMessages = $messages->items();
        $formattedMessages = array_map(function ($message) use ($userId) {
            return [
                'id' => $message->id,
                'text' => $message->content,
                'senderId' => $message->sender_id,
                'senderName' => $message->sender->name,
                'senderAvatar' => $message->sender->avatar_url ?? 'https://i.pravatar.cc/150?u=' . $message->sender_id,
                'timestamp' => $message->created_at->toISOString(),
                'isCurrentUser' => $message->sender_id === $userId,
                'isRead' => $message->is_read,
            ];
        }, $formattedMessages);

        // Mark messages as read
        MessageModel::where('chat_id', $chatId)
               ->where('sender_id', '!=', $userId)
               ->where('is_read', false)
               ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'messages' => array_reverse($formattedMessages),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'total' => $messages->total(),
                'has_more' => $messages->hasMorePages()
            ]
        ]);
    }


    /**
     * Search chats and messages
     */
    public function searchChats(Request $request)
    {
        $query = $request->get('query', '');
        $userId = Auth::id();

        if (empty($query)) {
            return $this->getChats();
        }

        $chats = ChatModel::where('user1_id', $userId)
                    ->orWhere('user2_id', $userId)
                    ->with(['user1', 'user2', 'lastMessage'])
                    ->get()
                    ->map(function ($chat) use ($userId) {
                        $otherUser = $chat->user1_id === $userId ? $chat->user2 : $chat->user1;
                        return [
                            'id' => $chat->id,
                            'name' => $otherUser->name,
                            'message' => $chat->lastMessage ? $chat->lastMessage->content : 'No messages yet',
                            'time' => $chat->lastMessage ? $chat->lastMessage->created_at->diffForHumans() : 'Never',
                            'avatar' => $otherUser->avatar_url ?? 'https://i.pravatar.cc/150?u=' . $otherUser->id,
                            'unreadCount' => 0,
                            'isOnline' => $otherUser->is_online ?? false,
                            'other_user_id' => $otherUser->id,
                        ];
                    })
                    ->filter(function ($chat) use ($query) {
                        return stripos($chat['name'], $query) !== false || 
                               stripos($chat['message'], $query) !== false;
                    })
                    ->values();

        return response()->json([
            'success' => true,
            'chats' => $chats
        ]);
    }

    /**
     * Get user's online status
     */
    public function getUserStatus($userId)
    {
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'isOnline' => $user->is_online ?? false,
                'lastSeen' => $user->last_seen_at,
                'avatar' => $user->avatar_url ?? 'https://i.pravatar.cc/150?u=' . $user->id,
            ]
        ]);
    }

    /**
     * Update user's online status
     */
    public function updateOnlineStatus(Request $request)
    {
        $request->validate([
            'is_online' => 'required|boolean'
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->update([
            'is_online' => $request->is_online,
            'last_seen_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
    }

    /**
     * Broadcast message to Socket.IO
     * You'll need to implement this based on your Socket.IO server setup
     */
    private function broadcastMessage($messageData)
    {
        // If using Laravel Broadcasting with Redis
        // broadcast(new MessageSent($messageData));
        
        // Or if using direct Redis connection
        // Redis::publish('chat_messages', json_encode($messageData));
        
        // For now, this is a placeholder
        // You'll need to implement according to your Socket.IO server setup
    }
}






// ==============================================
// 4. SOCKET.IO SERVER (Node.js - server.js)
// ==============================================

/*
const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');

const app = express();
const server = http.createServer(app);
const io = socketIo(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

app.use(cors());
app.use(express.json());

// Store connected users
const connectedUsers = new Map();

io.on('connection', (socket) => {
    console.log('User connected:', socket.id);

    // Handle user joining
    socket.on('join_user', (userId) => {
        connectedUsers.set(userId, socket.id);
        socket.userId = userId;
        console.log(`User ${userId} joined`);
    });

    // Handle sending messages
    socket.on('send_message', (data) => {
        const { message, receiverId, senderId, chatId } = data;
        
        // Get receiver's socket ID
        const receiverSocketId = connectedUsers.get(receiverId);
        
        const messageData = {
            id: Date.now().toString(),
            text: message,
            senderId: senderId,
            receiverId: receiverId,
            chatId: chatId,
            timestamp: new Date().toISOString(),
            isCurrentUser: false
        };

        // Send to receiver if online
        if (receiverSocketId) {
            io.to(receiverSocketId).emit('receive_message', messageData);
        }

        // Send confirmation back to sender
        socket.emit('message_sent', {
            ...messageData,
            isCurrentUser: true
        });
    });

    // Handle user typing
    socket.on('typing', (data) => {
        const { receiverId, isTyping } = data;
        const receiverSocketId = connectedUsers.get(receiverId);
        
        if (receiverSocketId) {
            io.to(receiverSocketId).emit('user_typing', {
                userId: socket.userId,
                isTyping: isTyping
            });
        }
    });

    // Handle disconnection
    socket.on('disconnect', () => {
        if (socket.userId) {
            connectedUsers.delete(socket.userId);
            console.log(`User ${socket.userId} disconnected`);
        }
    });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`Socket.IO server running on port ${PORT}`);
});
*/

// ==============================================
// 5. MIGRATIONS
// ==============================================

/*
// Create chats table migration
php artisan make:migration create_chats_table

Schema::create('chats', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user1_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('user2_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('last_message_id')->nullable()->constrained('messages')->onDelete('set null');
    $table->timestamps();
    
    $table->unique(['user1_id', 'user2_id']);
});

// Create messages table migration
php artisan make:migration create_messages_table

Schema::create('messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('chat_id')->constrained()->onDelete('cascade');
    $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
    $table->text('content');
    $table->boolean('is_read')->default(false);
    $table->enum('message_type', ['text', 'image', 'file', 'audio'])->default('text');
    $table->timestamps();
    
    $table->index(['chat_id', 'created_at']);
});

// Add columns to users table
php artisan make:migration add_chat_fields_to_users_table

Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_online')->default(false);
    $table->timestamp('last_seen_at')->nullable();
    $table->string('avatar_url')->nullable();
});
*/