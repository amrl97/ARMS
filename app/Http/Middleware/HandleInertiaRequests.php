<?php

namespace App\Http\Middleware;

use App\Enums\AccessRequestDetailStatus;
use App\Enums\ApprovalStatus;
use App\Models\AccessRequestApproval;
use App\Models\User;
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

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'roles' => fn (): array => $user instanceof User
                    ? $user->getRoleNames()->all()
                    : [],
                'canApprove' => fn (): bool => $user instanceof User
                    && AccessRequestApproval::query()
                        ->where('approver_id', $user->getKey())
                        ->where('status', ApprovalStatus::Pending->value)
                        ->whereHas(
                            'requestDetail',
                            fn ($details) => $details->where(
                                'status',
                                AccessRequestDetailStatus::Pending->value,
                            ),
                        )
                        ->exists(),
            ],
        ];
    }
}
