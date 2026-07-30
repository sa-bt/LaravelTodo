<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePageViewRequest;
use App\Models\User;
use App\Services\VisitTrackingService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class PageViewController extends Controller
{
    public function __construct(private VisitTrackingService $tracking) {}

    /**
     * Records a page view reported by the browser.
     *
     * Open on purpose: most views come from visitors who have never signed in,
     * and the landing page is the single most interesting thing to measure. A
     * rate limit on the route keeps the cost of that openness bounded.
     *
     * The response is empty. The browser sends this with a beacon and never
     * looks at what comes back, so a body would only be bytes nobody reads.
     */
    public function store(StorePageViewRequest $request): Response
    {
        $this->tracking->record(
            $request->validated(),
            $request->userAgent(),
            $this->resolveUser()
        );

        return response()->noContent();
    }

    /**
     * The route sits outside the auth group, so the default guard sees nobody
     * even when a signed in user is browsing. Asking the token guard directly
     * is what separates a member view from a guest view.
     */
    private function resolveUser(): ?User
    {
        $user = Auth::guard('sanctum')->user();

        return $user instanceof User ? $user : null;
    }
}
