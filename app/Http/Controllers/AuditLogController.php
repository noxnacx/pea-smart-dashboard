<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        // สร้าง Cache Key
        $filterKey = 'audit_logs_' . md5(json_encode($request->all()));

        $logs = Cache::remember($filterKey, 30, function () use ($request) {

            $query = AuditLog::with('user');

            // 1. กรองชื่อผู้ใช้
            if ($request->filled('user_search')) {
                $query->whereHas('user', function($q) use ($request) {
                    $q->where('name', 'ilike', '%' . $request->user_search . '%');
                });
            }

            // 2. กรอง Action
            if ($request->filled('action')) {
                if ($request->action === 'EXPORT') {
                    // รวม EXPORT และ DOWNLOAD ไว้ด้วยกัน
                    $query->whereIn('action', ['EXPORT', 'DOWNLOAD']);
                } else {
                    // กรณีอื่นๆ (CREATE, UPDATE, UPDATE_PROGRESS, DELETE, etc.)
                    $query->where('action', $request->action);
                }
            }

            // 3. กรอง Model
            if ($request->filled('model')) {
                $query->where('model_type', $request->model);
            }

            // 4. กรองวันที่
            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            return $query->orderBy('created_at', 'desc')
                ->paginate(20)
                ->withQueryString();
        });

        return Inertia::render('System/AuditLogs', [
            'logs' => $logs,
            'filters' => $request->all(['user_search', 'action', 'model', 'start_date', 'end_date']),
        ]);
    }
}
