<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        // 1. เริ่ม Query
        $query = AuditLog::with('user'); // ดึงข้อมูล User (รวม Role) มาด้วย

        // 2. ตัวกรอง: ค้นหาชื่อผู้ใช้ (Search User Name)
        if ($request->filled('user_search')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'ilike', '%' . $request->user_search . '%');
            });
        }

        // 3. ตัวกรอง: การกระทำ (Action)
        if ($request->filled('action')) {
            if ($request->action === 'DOWNLOAD_ALL') {
                $query->whereIn('action', ['EXPORT', 'DOWNLOAD']);
            } else {
                $query->where('action', $request->action);
            }
        }

        // 4. ตัวกรอง: ประเภทข้อมูล (Model)
        if ($request->filled('model')) {
            $query->where('model_type', $request->model);
        }

        // 5. ✅ ปรับปรุง: ตัวกรองช่วงวันที่ (Date Range)
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // 6. ดึงข้อมูล
        $logs = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('System/AuditLogs', [
            'logs' => $logs,
            // ✅ ส่งค่า start_date, end_date กลับไปหน้าบ้านแทน date ตัวเดียว
            'filters' => $request->all(['user_search', 'action', 'model', 'start_date', 'end_date']),
        ]);
    }
}
