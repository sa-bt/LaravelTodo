<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription.endpoint' => ['required', 'string'],
            'subscription.keys.p256dh' => ['required', 'string'],
            'subscription.keys.auth' => ['required', 'string'],
        ]);

        $subscription = $validated['subscription'];

        $request->user()->updatePushSubscription(
            $subscription['endpoint'],
            $subscription['keys']['p256dh'],
            $subscription['keys']['auth']
        );

        return response()->json([
            'success' => true,
            'message' => 'Push subscription saved successfully',
        ]);
    }
}
