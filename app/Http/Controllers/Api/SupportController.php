<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    /**
     * Get all support messages for the authenticated customer.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        // Fetch messages
        $messages = SupportMessage::where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->get();

        // Mark as read by user
        SupportMessage::where('user_id', $userId)
            ->where('sender_id', '!=', $userId)
            ->where('is_read_by_user', false)
            ->update(['is_read_by_user' => true]);

        return response()->json($messages);
    }

    /**
     * Store a new support message from the customer.
     */
    public function store(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $userId = $request->user()->id;

        $message = SupportMessage::create([
            'user_id' => $userId,
            'sender_id' => $userId,
            'message' => $request->input('message'),
            'is_read_by_user' => true,
            'is_read_by_admin' => false,
        ]);

        return response()->json($message, 201);
    }
}
