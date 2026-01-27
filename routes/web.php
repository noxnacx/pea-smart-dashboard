<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkItemController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\GlobalSearchController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes (หน้าบ้าน)
|--------------------------------------------------------------------------
*/
Route::get('/', [DashboardController::class, 'publicDashboard'])->name('dashboard.public');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (ต้อง Login ก่อน)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {

    // --- 1. Dashboard ---
    Route::get('/admin/dashboard', [DashboardController::class, 'adminDashboard'])->name('dashboard');

    // --- 2. Work Items ---
    Route::post('/work-items', [WorkItemController::class, 'store'])->name('work-items.store');
    Route::put('/work-items/{workItem}', [WorkItemController::class, 'update'])->name('work-items.update');
    Route::delete('/work-items/{workItem}', [WorkItemController::class, 'destroy'])->name('work-items.destroy');
    Route::get('/work-items/{workItem}', [WorkItemController::class, 'show'])->name('work-items.show');
    Route::resource('work-items', WorkItemController::class);

    // --- 3. Attachments ---
    Route::post('/work-items/{workItem}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // --- 4. User Management ---
    Route::resource('users', UserController::class)->except(['create', 'edit', 'show']);

    // --- 5. Reports (ระบบรายงาน) ---
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    // Export PDF รายงานเจาะจงรายโครงการ/งาน
    Route::get('/work-items/{workItem}/export-pdf', [ReportController::class, 'exportWorkItemPdf'])
    ->name('work-items.export-pdf');
    Route::get('/calendar/export-agenda', [CalendarController::class, 'exportAgendaPdf'])
    ->name('calendar.export-agenda');


    // 5.1 Project Progress
    Route::get('/reports/progress/pdf', [ReportController::class, 'exportProgressPdf'])->name('reports.progress.pdf');
    Route::get('/reports/progress/excel', [ReportController::class, 'exportProgressExcel'])->name('reports.progress.excel');
    Route::get('/reports/progress/csv', [ReportController::class, 'exportProgressCsv'])->name('reports.progress.csv'); // ✨ เพิ่มบรรทัดนี้

    // 5.2 Issues & Risks
    Route::get('/reports/issues/pdf', [ReportController::class, 'exportIssuesPdf'])->name('reports.issues.pdf');
    Route::get('/reports/issues/excel', [ReportController::class, 'exportIssuesExcel'])->name('reports.issues.excel');
    Route::get('/reports/issues/csv', [ReportController::class, 'exportIssuesCsv'])->name('reports.issues.csv'); // ✨ เพิ่มบรรทัดนี้

    // 5.3 Executive Summary
    Route::get('/reports/executive/pdf', [ReportController::class, 'exportExecutivePdf'])->name('reports.executive.pdf');
    Route::get('/reports/executive/excel', [ReportController::class, 'exportExecutiveExcel'])->name('reports.executive.excel');
    Route::get('/reports/executive/csv', [ReportController::class, 'exportExecutiveCsv'])->name('reports.executive.csv'); // ✨ เพิ่มบรรทัดนี้

    // --- 6. User Profile ---
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // --- Other Shortcuts ---
    Route::get('/plans', [WorkItemController::class, 'list'])->defaults('type', 'plan')->name('plans.index');
    Route::get('/projects', [WorkItemController::class, 'list'])->defaults('type', 'project')->name('projects.index');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // --- Comments ---
    Route::post('/work-items/{workItem}/comments', [\App\Http\Controllers\CommentController::class, 'store'])->name('comments.store');

    // --- Issues / Risks ---
    Route::post('/work-items/{workItem}/issues', [App\Http\Controllers\IssueController::class, 'store'])->name('issues.store');
    Route::put('/issues/{issue}', [App\Http\Controllers\IssueController::class, 'update'])->name('issues.update');
    Route::delete('/issues/{issue}', [App\Http\Controllers\IssueController::class, 'destroy'])->name('issues.destroy');

    // --- Strategy Pages ---
    Route::get('/strategies', [WorkItemController::class, 'strategies'])->name('strategies.index');
    Route::get('/plans', [WorkItemController::class, 'plans'])->name('plans.index');
    Route::get('/projects', [WorkItemController::class, 'projects'])->name('projects.index');

    // --- Calendar ---
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/global-search', [GlobalSearchController::class, 'search'])->name('global.search');

    Route::get('/work-items/{workItem}/gantt-data', [App\Http\Controllers\WorkItemController::class, 'ganttData'])->name('work-items.gantt-data');
    Route::post('/work-items/{workItem}/log-export', [WorkItemController::class, 'logExport'])->name('work-items.log-export');
});

require __DIR__.'/auth.php';
