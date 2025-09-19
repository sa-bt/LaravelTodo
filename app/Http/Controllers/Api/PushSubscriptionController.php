<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    
    public function store(Request $request)
    {
        $user = Auth::user();

        $subscription = $request->input('subscription', []);

        $endpoint = $subscription['endpoint'] ?? null;
        $keys = $subscription['keys'] ?? [];

        $p256dh = $keys['p256dh'] ?? null;
        $auth = $keys['auth'] ?? null;

        // اعتبارسنجی داده‌ها
        if (!$endpoint || !$p256dh || !$auth) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription data invalid'
            ], 422);
        }

        // ثبت یا بروزرسانی subscription
        $user->updatePushSubscription($endpoint, $p256dh, $auth);

        return response()->json([
            'success' => true,
            'message' => 'Push subscription saved successfully'
        ]);
    }
    }
