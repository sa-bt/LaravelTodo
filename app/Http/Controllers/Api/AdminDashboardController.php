<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Content;
use App\Models\Goal;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $today = now()->toDateString();
        $weekStart = now()->subDays(6)->startOfDay();

        return $this->successResponse([
            'users' => [
                'total' => User::query()->count(),
                'new_today' => User::query()->whereDate('created_at', $today)->count(),
                'new_last_7_days' => User::query()->where('created_at', '>=', $weekStart)->count(),
                'verified' => User::query()->whereNotNull('email_verified_at')->count(),
                'pending_approval' => User::query()
                    ->whereNull('approved_at')
                    ->whereNull('rejected_at')
                    ->count(),
            ],
            'activity' => [
                'goals' => Goal::query()->count(),
                'tasks' => Task::query()->count(),
                'tasks_today' => Task::query()->whereDate('day', $today)->count(),
                'completed_today' => Task::query()
                    ->whereDate('day', $today)
                    ->where('is_done', true)
                    ->count(),
            ],
            'contacts' => [
                'total' => Contact::query()->count(),
                'new' => Contact::query()->where('status', 'new')->count(),
            ],
            'contents' => [
                'total' => Content::query()->count(),
                'draft' => Content::query()->where('status', Content::STATUS_DRAFT)->count(),
                'ready' => Content::query()->where('status', Content::STATUS_READY)->count(),
            ],
        ]);
    }
}
