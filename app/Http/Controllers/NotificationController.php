<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    // 1. แสดงหน้ารายการแจ้งเตือนทั้งหมด
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->paginate(15);
        return Inertia::render('Notification/Index', [
            'notifications' => $notifications,
        ]);
    }

    // 2. กดอ่านทีละรายการ
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }
        return back();
    }

    // 3. กดอ่านทั้งหมดรวดเดียว
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return back()->with('success', 'ทำเครื่องหมายอ่านแล้วทั้งหมด');
    }

    // 🚀 4. ฟังก์ชันลบการแจ้งเตือน (ที่เพิ่มเข้ามาใหม่)
    public function destroy(Request $request, $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->delete();
        }
        return back()->with('success', 'ลบการแจ้งเตือนเรียบร้อยแล้ว');
    }
}
