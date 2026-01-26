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
                // ✨ หาที่ Action เป็น EXPORT หรือ DOWNLOAD (รวมทั้งหมดในปุ่มเดียว)
                $query->whereIn('action', ['EXPORT', 'DOWNLOAD']);
            } else {
                $query->where('action', $request->action);
            }
        }

        // 4. ตัวกรอง: ประเภทข้อมูล (Model)
        if ($request->filled('model')) {
            $query->where('model_type', $request->model);
        }

        // 5. ตัวกรอง: วันที่ (Date)
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // 6. ดึงข้อมูล
        $logs = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('System/AuditLogs', [
            'logs' => $logs,
            'filters' => $request->all(['user_search', 'action', 'model', 'date']),
        ]);
    }
}
