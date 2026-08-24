<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        // Get all notifications for the user
        $notifications = $request->user()->notifications()->paginate(10);

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

    public function destroy(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        return redirect()->back();
    }

    public function destroyAll(Request $request)
    {
        $request->user()->notifications()->delete();

        return redirect()->back()->with('message', 'Todas las notificaciones fueron eliminadas.');
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->back()->with('message', 'Todas las notificaciones fueron marcadas como leídas.');
    }
}
