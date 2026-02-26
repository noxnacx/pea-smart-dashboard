<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        // 🚀 เอา Cache ออก ดึงสด 100%
        $query = AuditLog::with('user');

        if ($request->filled('user_search')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'ilike', '%' . $request->user_search . '%');
            });
        }

        if ($request->filled('action')) {
            if ($request->action === 'EXPORT') {
                $query->whereIn('action', ['EXPORT', 'DOWNLOAD']);
            } else {
                $query->where('action', $request->action);
            }
        }

        if ($request->filled('model')) {
            $query->where('model_type', $request->model);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('System/AuditLogs', [
            'logs' => $logs,
            'filters' => $request->all(['user_search', 'action', 'model', 'start_date', 'end_date']),
        ]);
    }
}
