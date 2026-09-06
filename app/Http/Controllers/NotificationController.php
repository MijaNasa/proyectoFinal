<?php

namespace App\Http\Controllers;

use App\Notifications\TrasladoPendienteVenta;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user) {
            TrasladoPendienteVenta::marcarLeidasSiCompletado();
        }
        $notifications = $user ? $user->notifications()->paginate(10) : null;
        $unreadCount = $user ? $user->unreadNotifications()->count() : 0;

        return inertia('Notificaciones/Index', [
            'notificaciones' => $notifications,
            'unreadCount' => $unreadCount,
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
