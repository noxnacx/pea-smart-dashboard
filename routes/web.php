<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkItemController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// หน้าแรก -> ไปที่ publicDashboard
Route::get('/', [DashboardController::class, 'publicDashboard'])->name('dashboard.public');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Admin)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    // *** จุดที่แก้: เปลี่ยนให้ไปใช้ DashboardController เพื่อให้ได้กราฟ ***
    Route::get('/admin/dashboard', [DashboardController::class, 'adminDashboard'])->name('dashboard');

    // CRUD Routes (ยังใช้ WorkItemController เหมือนเดิมสำหรับการ บันทึก/ลบ/แก้ไข)
    Route::post('/work-items', [WorkItemController::class, 'store'])->name('work-items.store');
    Route::put('/work-items/{workItem}', [WorkItemController::class, 'update'])->name('work-items.update');
    Route::delete('/work-items/{workItem}', [WorkItemController::class, 'destroy'])->name('work-items.destroy');

    // รายละเอียดโครงการ
    Route::get('/work-items/{workItem}', [WorkItemController::class, 'show'])->name('work-items.show');

    // Attachments
    Route::post('/work-items/{workItem}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('users', UserController::class)->except(['create', 'edit', 'show']);
});

require __DIR__.'/auth.php';
