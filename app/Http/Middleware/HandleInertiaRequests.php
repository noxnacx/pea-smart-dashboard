<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'roles' => [
                    'is_admin' => $request->user()?->isAdmin() ?? false,
                    'is_pm' => $request->user()?->isPm() ?? false,
                    'is_user' => $request->user()?->isGeneralUser() ?? false,
                ],
                // ส่งสิทธิ์การกระทำไป
                'can' => [
                    'edit' => $request->user()?->canEdit() ?? false,
                    'manage_users' => $request->user()?->isAdmin() ?? false,
                    'manage_org' => $request->user()?->isAdmin() ?? false,
                ]
            ],
        ];
    }
}
