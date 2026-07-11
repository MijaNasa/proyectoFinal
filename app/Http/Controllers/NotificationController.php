<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        // Get all unread notifications for the user
        $notifications = $request->user()->unreadNotifications()->paginate(10);

        return inertia('Notificaciones/Index', [
            'notificaciones' => $notifications
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead(); // This updates read_at

        return redirect()->back();
    }
}
