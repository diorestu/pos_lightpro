<?php

namespace App\Http\Middleware;

use App\Models\CashierShift;
use App\Models\ReceiptSetting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $activeShift = null;

        if ($user) {
            $activeShift = CashierShift::where('user_id', $user->id)
                ->where('status', 'open')
                ->latest('opened_at')
                ->first();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'active_shift' => $activeShift,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'receipt_settings' => ReceiptSetting::getActive(),
            'sidebarOpen' => $request->hasCookie('sidebar_state') && $request->cookie('sidebar_state') === 'true',
        ];
    }
}
