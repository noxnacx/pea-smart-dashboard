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
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProjectManagerController;
use App\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Public Routes (หน้าบ้าน)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes (ต้อง Login ก่อน)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {

    // =========================================================================
    // 🌍 1. General Access (เข้าถึงได้ทุกคนที่ Login)
    // =========================================================================

    // --- Dashboard ---
    Route::get('/admin/dashboard', [DashboardController::class, 'adminDashboard'])->name('dashboard');

    // --- Work Items Read-Only (รายการงาน) ---
    Route::get('/work-items', [WorkItemController::class, 'index'])->name('work-items.index');

    // ✅ เพิ่ม Route: งานของฉัน (My Works)
    Route::get('/my-works', [WorkItemController::class, 'myWorks'])->name('my-works.index');

    Route::get('/plans', [WorkItemController::class, 'list'])->defaults('type', 'plan')->name('plans.index');
    Route::get('/projects', [WorkItemController::class, 'list'])->defaults('type', 'project')->name('projects.index');
    Route::get('/strategies', [WorkItemController::class, 'strategies'])->name('strategies.index');
    Route::get('/tasks', [WorkItemController::class, 'list'])->defaults('type', 'task')->name('tasks.index');
    Route::get('/issues', [IssueController::class, 'index'])->name('issues.index');

    // ✅ Route สำหรับดึง Tree
    Route::get('/api/strategies/tree', [WorkItemController::class, 'getTree'])->name('api.strategies.tree');

    // ✅ Detail Page
    Route::get('/work-items/{workItem}', [WorkItemController::class, 'show'])
        ->name('work-items.show')
        ->whereNumber('workItem');
    Route::post('/work-items/{workItem}/update-progress', [WorkItemController::class, 'updateProgress'])->name('work-items.update-progress');

    // --- Reports & Tools ---
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/global-search', [GlobalSearchController::class, 'search'])->name('global.search');
    Route::get('/project-managers', [ProjectManagerController::class, 'index'])->name('pm.index');
    Route::get('/project-managers/{id}', [ProjectManagerController::class, 'show'])->name('pm.show');

    // --- Comments ---
    Route::post('/work-items/{workItem}/comments', [CommentController::class, 'store'])->name('comments.store');

    // --- User Profile ---
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    // =========================================================================
    // 📤 2. Exports & Data API
    // =========================================================================

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/progress/pdf', [ReportController::class, 'exportProgressPdf'])->name('progress.pdf');
        Route::get('/progress/excel', [ReportController::class, 'exportProgressExcel'])->name('progress.excel');
        Route::get('/progress/csv', [ReportController::class, 'exportProgressCsv'])->name('progress.csv');

        Route::get('/issues/pdf', [ReportController::class, 'exportIssuesPdf'])->name('issues.pdf');
        Route::get('/issues/excel', [ReportController::class, 'exportIssuesExcel'])->name('issues.excel');
        Route::get('/issues/csv', [ReportController::class, 'exportIssuesCsv'])->name('issues.csv');

        Route::get('/executive/pdf', [ReportController::class, 'exportExecutivePdf'])->name('executive.pdf');
        Route::get('/executive/excel', [ReportController::class, 'exportExecutiveExcel'])->name('executive.excel');
        Route::get('/executive/csv', [ReportController::class, 'exportExecutiveCsv'])->name('executive.csv');
    });

    Route::get('/work-items/{workItem}/export-pdf', [ReportController::class, 'exportWorkItemPdf'])->name('work-items.export-pdf');
    Route::get('/calendar/export-agenda', [CalendarController::class, 'exportAgendaPdf'])->name('calendar.export-agenda');
    Route::post('/work-items/{workItem}/log-export', [WorkItemController::class, 'logExport'])->name('work-items.log-export');
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');

    Route::get('/work-items/{workItem}/gantt-data', [WorkItemController::class, 'ganttData'])->name('work-items.gantt-data');
    Route::get('/api/project-managers/search', [WorkItemController::class, 'searchProjectManagers'])->name('api.pm.search');


    // =========================================================================
    // 🛠️ 3. Editor Access (Admin & PM Only)
    // =========================================================================
    Route::middleware(['can:manage-work'])->group(function () {

        // --- Work Items (CRUD) ---
        Route::get('/work-items/create', [WorkItemController::class, 'create'])->name('work-items.create');
        Route::post('/work-items', [WorkItemController::class, 'store'])->name('work-items.store');
        Route::get('/work-items/{workItem}/edit', [WorkItemController::class, 'edit'])->name('work-items.edit');
        Route::put('/work-items/{workItem}', [WorkItemController::class, 'update'])->name('work-items.update');
        Route::delete('/work-items/{workItem}', [WorkItemController::class, 'destroy'])->name('work-items.destroy');

        Route::put('/work-items/{workItem}/move', [WorkItemController::class, 'move'])->name('work-items.move');

        Route::post('/work-items/{workItem}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
        Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

        Route::post('/work-items/{workItem}/issues', [IssueController::class, 'store'])->name('issues.store');
        Route::put('/issues/{issue}', [IssueController::class, 'update'])->name('issues.update');
        Route::delete('/issues/{issue}', [IssueController::class, 'destroy'])->name('issues.destroy');

    });


    // =========================================================================
    // 🛡️ 4. Admin Only Access (เฉพาะ Admin เท่านั้น)
    // =========================================================================
    Route::middleware(['can:manage-system'])->group(function () {

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::resource('users', UserController::class)->except(['create', 'edit', 'show']);
        Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');

        Route::post('/divisions', [OrganizationController::class, 'storeDivision'])->name('divisions.store');
        Route::put('/divisions/{division}', [OrganizationController::class, 'updateDivision'])->name('divisions.update');
        Route::delete('/divisions/{division}', [OrganizationController::class, 'destroyDivision'])->name('divisions.destroy');

        Route::post('/departments', [OrganizationController::class, 'storeDepartment'])->name('departments.store');
        Route::put('/departments/{department}', [OrganizationController::class, 'updateDepartment'])->name('departments.update');
        Route::delete('/departments/{department}', [OrganizationController::class, 'destroyDepartment'])->name('departments.destroy');

        Route::delete('/pm/{id}', [ProjectManagerController::class, 'destroy'])->name('pm.destroy');

    });

});

require __DIR__.'/auth.php';
