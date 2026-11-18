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
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'pendingIssuesCount' => fn () => $request->user()
                ? ($request->user()->isSuperadmin()
                    ? \App\Models\IssueReport::where('status', 'Menunggu')->count()
                    : \App\Models\IssueReport::where('user_id', $request->user()->id)->whereIn('status', ['Menunggu', 'Diproses'])->count()
                ) : 0,
            'pendingSubmissionsCount' => fn () => $request->user() && $request->user()->isSuperadmin()
                ? \App\Models\RegistrationSubmission::where('status', 'pending')->count()
                : 0,
        ];
    }
}
