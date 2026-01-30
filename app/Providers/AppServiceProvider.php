<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Models\WorkItem;
use App\Policies\WorkItemPolicy;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Attachment;
use App\Observers\GlobalObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Gate::policy(WorkItem::class, WorkItemPolicy::class);
        WorkItem::observe(GlobalObserver::class);
        User::observe(GlobalObserver::class);
        Attachment::observe(GlobalObserver::class);

        // ✅ กำหนด Gate สำหรับ Admin เท่านั้น (ใช้กับ can:manage-system)
        Gate::define('manage-system', function (User $user) {
            return $user->isAdmin();
        });

        // ✅ กำหนด Gate สำหรับคนที่มีสิทธิ์แก้ไขงาน (Admin หรือ PM) (ใช้กับ can:manage-work)
        Gate::define('manage-work', function (User $user) {
            return $user->canEdit();
        });
    }
}
