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
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Public Routes (หน้าบ้าน)
|--------------------------------------------------------------------------
*/
// เมื่อเข้าหน้าแรก (Root URL) ให้ Redirect ไปหน้า Login ทันที
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

    // --- Read-Only Work Items (ดูข้อมูลได้ทุกคน) ---
    // ✅ แก้ไข: เพิ่ม whereNumber เพื่อไม่ให้แย่ง Route 'create'
    Route::get('/work-items/{workItem}', [WorkItemController::class, 'show'])
        ->name('work-items.show')
        ->whereNumber('workItem');

    Route::get('/work-items', [WorkItemController::class, 'index'])->name('work-items.index');
    Route::get('/plans', [WorkItemController::class, 'list'])->defaults('type', 'plan')->name('plans.index');
    Route::get('/projects', [WorkItemController::class, 'list'])->defaults('type', 'project')->name('projects.index');
    Route::get('/strategies', [WorkItemController::class, 'strategies'])->name('strategies.index');

    // --- Reports & Calendar ---
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/global-search', [GlobalSearchController::class, 'search'])->name('global.search');

    // --- API / Exports ---
    Route::get('/work-items/{workItem}/export-pdf', [ReportController::class, 'exportWorkItemPdf'])->name('work-items.export-pdf');
    Route::get('/calendar/export-agenda', [CalendarController::class, 'exportAgendaPdf'])->name('calendar.export-agenda');

    Route::get('/reports/progress/pdf', [ReportController::class, 'exportProgressPdf'])->name('reports.progress.pdf');
    Route::get('/reports/progress/excel', [ReportController::class, 'exportProgressExcel'])->name('reports.progress.excel');
    Route::get('/reports/progress/csv', [ReportController::class, 'exportProgressCsv'])->name('reports.progress.csv');

    Route::get('/reports/issues/pdf', [ReportController::class, 'exportIssuesPdf'])->name('reports.issues.pdf');
    Route::get('/reports/issues/excel', [ReportController::class, 'exportIssuesExcel'])->name('reports.issues.excel');
    Route::get('/reports/issues/csv', [ReportController::class, 'exportIssuesCsv'])->name('reports.issues.csv');

    Route::get('/reports/executive/pdf', [ReportController::class, 'exportExecutivePdf'])->name('reports.executive.pdf');
    Route::get('/reports/executive/excel', [ReportController::class, 'exportExecutiveExcel'])->name('reports.executive.excel');
    Route::get('/reports/executive/csv', [ReportController::class, 'exportExecutiveCsv'])->name('reports.executive.csv');

    Route::get('/work-items/{workItem}/gantt-data', [WorkItemController::class, 'ganttData'])->name('work-items.gantt-data');
    Route::post('/work-items/{workItem}/log-export', [WorkItemController::class, 'logExport'])->name('work-items.log-export');
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');

    Route::get('/api/project-managers/search', [WorkItemController::class, 'searchProjectManagers'])->name('api.pm.search');

    // --- Project Managers List (ดูได้ทุกคน) ---
    Route::get('/project-managers', [ProjectManagerController::class, 'index'])->name('pm.index');
    Route::get('/project-managers/{id}', [ProjectManagerController::class, 'show'])->name('pm.show');

    // --- Profile ---
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // --- Comments (Everyone usually can comment) ---
    Route::post('/work-items/{workItem}/comments', [\App\Http\Controllers\CommentController::class, 'store'])->name('comments.store');


    // =========================================================================
    // 🛠️ 2. Editor Access (Admin & PM Only)
    // ✅ ใช้ Gate 'manage-work' ที่เราสร้างไว้ใน AppServiceProvider
    // =========================================================================
    Route::middleware(['can:manage-work'])->group(function () {

        // --- Work Items (Create / Update / Delete) ---
        Route::get('/work-items/create', [WorkItemController::class, 'create'])->name('work-items.create');
        Route::post('/work-items', [WorkItemController::class, 'store'])->name('work-items.store');
        Route::get('/work-items/{workItem}/edit', [WorkItemController::class, 'edit'])->name('work-items.edit');
        Route::put('/work-items/{workItem}', [WorkItemController::class, 'update'])->name('work-items.update');
        Route::delete('/work-items/{workItem}', [WorkItemController::class, 'destroy'])->name('work-items.destroy');

        // --- Attachments (Upload / Delete) ---
        Route::post('/work-items/{workItem}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
        Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

        // --- Issues / Risks (Manage) ---
        Route::post('/work-items/{workItem}/issues', [IssueController::class, 'store'])->name('issues.store');
        Route::put('/issues/{issue}', [IssueController::class, 'update'])->name('issues.update');
        Route::delete('/issues/{issue}', [IssueController::class, 'destroy'])->name('issues.destroy');

    });


    // =========================================================================
    // 🛡️ 3. Admin Only Access (เฉพาะ Admin เท่านั้น)
    // ✅ ใช้ Gate 'manage-system' ที่เราสร้างไว้ใน AppServiceProvider
    // =========================================================================
    Route::middleware(['can:manage-system'])->group(function () {

        // --- System Logs ---
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        // --- User Management ---
        Route::resource('users', UserController::class)->except(['create', 'edit', 'show']);

        // --- Organization Structure ---
        Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');

        Route::post('/divisions', [OrganizationController::class, 'storeDivision'])->name('divisions.store');
        Route::put('/divisions/{division}', [OrganizationController::class, 'updateDivision'])->name('divisions.update');
        Route::delete('/divisions/{division}', [OrganizationController::class, 'destroyDivision'])->name('divisions.destroy');

        Route::post('/departments', [OrganizationController::class, 'storeDepartment'])->name('departments.store');
        Route::put('/departments/{department}', [OrganizationController::class, 'updateDepartment'])->name('departments.update');
        Route::delete('/departments/{department}', [OrganizationController::class, 'destroyDepartment'])->name('departments.destroy');

        // --- PM Directory Management ---
        Route::delete('/pm/{id}', [ProjectManagerController::class, 'destroy'])->name('pm.destroy');

    });

});

require __DIR__.'/auth.php';
