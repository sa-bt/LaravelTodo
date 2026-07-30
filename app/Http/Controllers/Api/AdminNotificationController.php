<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendAdminNotificationRequest;
use App\Models\AdminNotificationCampaign;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Notifications\GenericWebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class AdminNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $campaigns = AdminNotificationCampaign::query()
            ->with('administrator:id,name')
            ->latest()
            ->paginate(min(max($request->integer('per_page', 15), 1), 50));

        return $this->successResponse($campaigns);
    }

    public function store(SendAdminNotificationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $query = User::query()->select(['id', 'name', 'email']);

        if ($data['audience'] === 'selected') {
            $query->whereKey($data['user_ids']);
        }

        $recipientCount = (clone $query)->count();
        $campaign = AdminNotificationCampaign::query()->create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'url' => $data['url'] ?? null,
            'channel' => $data['channel'],
            'audience' => $data['audience'],
            'recipient_count' => $recipientCount,
        ]);

        $notification = $data['channel'] === 'webpush'
            ? new GenericWebPush(
                $data['title'],
                $data['body'],
                $data['url'] ?? '/',
                ['type' => 'admin_announcement', 'campaign_id' => $campaign->id],
                tag: "admin-campaign-{$campaign->id}",
            )
            : new GenericDatabaseNotification(
                $data['title'],
                $data['body'],
                $data['url'] ?? '/',
                ['type' => 'admin_announcement', 'campaign_id' => $campaign->id],
            );

        $query->chunkById(200, fn ($users) => Notification::send($users, $notification));

        return $this->successResponse(
            $campaign->load('administrator:id,name'),
            'اعلان برای ارسال در صف قرار گرفت.',
            201,
        );
    }
}
