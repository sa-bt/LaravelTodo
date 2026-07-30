<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountApprovalDecided;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['admin', 'user'])],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->withCount(['goals', 'contents'])
            ->withCount(['goals as tasks_count' => fn ($query) => $query->join('tasks', 'goals.id', '=', 'tasks.goal_id')])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->input('role')))
            ->when($request->filled('status'), fn ($query) => match ($request->input('status')) {
                'pending' => $query->whereNull('approved_at')->whereNull('rejected_at'),
                'approved' => $query->whereNotNull('approved_at')->whereNull('rejected_at'),
                'rejected' => $query->whereNotNull('rejected_at'),
            })
            // حساب‌های در انتظار اول بیایند؛ صف تأیید همان چیزی است که ادمین
            // برای آن این صفحه را باز می‌کند.
            ->orderByRaw('CASE WHEN approved_at IS NULL AND rejected_at IS NULL THEN 0 ELSE 1 END')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->successResponse($users);
    }

    public function approve(Request $request, int $user): JsonResponse
    {
        $target = User::query()->findOrFail($user);

        if ($target->isApproved()) {
            return $this->errorResponse([], 'این حساب از قبل تأیید شده است.', 409);
        }

        $target->approveBy($request->user());
        $target->notify(new AccountApprovalDecided(approved: true));

        return $this->successResponse($target->fresh(), 'حساب کاربر تأیید شد.');
    }

    public function reject(Request $request, int $user): JsonResponse
    {
        $target = User::query()->findOrFail($user);

        if ($request->user()->is($target)) {
            return $this->errorResponse([], 'امکان رد کردن حساب فعلی وجود ندارد.', 422);
        }

        if ($target->role === 'admin') {
            return $this->errorResponse([], 'حساب مدیر قابل رد کردن نیست.', 422);
        }

        if ($target->isRejected()) {
            return $this->errorResponse([], 'این حساب از قبل رد شده است.', 409);
        }

        $target->rejectBy($request->user());
        $target->notify(new AccountApprovalDecided(approved: false));

        return $this->successResponse($target->fresh(), 'درخواست کاربر رد شد.');
    }

    public function show(int $user): JsonResponse
    {
        $user = User::query()->findOrFail($user);
        $user->loadCount(['goals', 'contents']);
        $user->loadCount(['goals as tasks_count' => fn ($query) => $query->join('tasks', 'goals.id', '=', 'tasks.goal_id')]);
        $user->loadCount(['goals as completed_tasks_count' => fn ($query) => $query
            ->join('tasks', 'goals.id', '=', 'tasks.goal_id')
            ->where('tasks.is_done', true)]);

        return $this->successResponse($user);
    }

    public function updateRole(Request $request, int $user): JsonResponse
    {
        $user = User::query()->findOrFail($user);
        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'user'])],
        ]);

        if ($request->user()->is($user)) {
            return $this->errorResponse([], 'امکان تغییر نقش حساب فعلی وجود ندارد.', 422);
        }

        if (
            $user->role === 'admin'
            && $data['role'] !== 'admin'
            && User::query()->where('role', 'admin')->count() <= 1
        ) {
            return $this->errorResponse([], 'آخرین مدیر سامانه قابل تغییر نیست.', 422);
        }

        $user->update(['role' => $data['role']]);

        if ($data['role'] !== 'admin') {
            $user->tokens()->delete();
        }

        return $this->successResponse($user->fresh(), 'نقش کاربر به‌روزرسانی شد.');
    }
}
