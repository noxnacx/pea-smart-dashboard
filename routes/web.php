<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkItemController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\IssueController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes (หน้าบ้าน)
|--------------------------------------------------------------------------
*/

// หน้าแรกสำหรับบุคคลทั่วไป
Route::get('/', [DashboardController::class, 'publicDashboard'])->name('dashboard.public');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (ต้อง Login ก่อน)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    // --- 1. Dashboard (ภาพรวม) ---
    Route::get('/admin/dashboard', [DashboardController::class, 'adminDashboard'])->name('dashboard');

    // --- 2. Work Items (จัดการงาน/โครงการ) ---
    // สร้าง / แก้ไข / ลบ
    Route::post('/work-items', [WorkItemController::class, 'store'])->name('work-items.store');
    Route::put('/work-items/{workItem}', [WorkItemController::class, 'update'])->name('work-items.update');
    Route::delete('/work-items/{workItem}', [WorkItemController::class, 'destroy'])->name('work-items.destroy');
    // ดูรายละเอียดเจาะลึก
    Route::get('/work-items/{workItem}', [WorkItemController::class, 'show'])->name('work-items.show');

    // --- 3. Attachments (ระบบไฟล์แนบ) ---
    Route::post('/work-items/{workItem}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // --- 4. User Management (จัดการผู้ใช้) ---
    Route::resource('users', UserController::class)->except(['create', 'edit', 'show']);

    // --- 5. Reports (รายงาน) ---
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf'])->name('reports.export');

    // --- 6. User Profile (ข้อมูลส่วนตัว) ---
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // หน้ารายการแผนงานทั้งหมด
    Route::get('/plans', [WorkItemController::class, 'list'])->defaults('type', 'plan')->name('plans.index');

    // หน้ารายการโครงการทั้งหมด
    Route::get('/projects', [WorkItemController::class, 'list'])->defaults('type', 'project')->name('projects.index');

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // --- Comments ---
    Route::post('/work-items/{workItem}/comments', [\App\Http\Controllers\CommentController::class, 'store'])->name('comments.store');

    // --- Issues / Risks ---
    Route::post('/work-items/{workItem}/issues', [App\Http\Controllers\IssueController::class, 'store'])->name('issues.store');
    Route::put('/issues/{issue}', [App\Http\Controllers\IssueController::class, 'update'])->name('issues.update');
    Route::delete('/issues/{issue}', [App\Http\Controllers\IssueController::class, 'destroy'])->name('issues.destroy');
});

require __DIR__.'/auth.php';
