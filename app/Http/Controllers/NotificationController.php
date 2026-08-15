<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->limit(30)
            ->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $request->user()->notifications()->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403, 'This notification does not belong to you.');
        }

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json($notification);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
