<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountApprovalDecided;
use App\Notifications\NewUserAwaitingApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserApprovalTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Passw0rd';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.captcha_pepper', 'test-pepper');
    }

    /** یک کپچای معتبر می‌سازد و ورودی‌های متناظرش را برمی‌گرداند. */
    private function captcha(string $answer = 'ABC23'): array
    {
        $id = bin2hex(random_bytes(16));
        Cache::put(
            "captcha:{$id}",
            hash('sha256', strtoupper($answer) . 'test-pepper'),
            now()->addMinutes(5)
        );

        return ['captcha_id' => $id, 'captcha_answer' => $answer];
    }

    /**
     * گاردها را فراموش می‌کند تا درخواست بعدی واقعاً از نو احراز هویت شود.
     *
     * در آزمون همهٔ درخواست‌ها یک نمونهٔ برنامه را به اشتراک می‌گذارند و گارد
     * کاربرِ حل‌شده را نگه می‌دارد. بدون این، توکنِ حذف‌شده همچنان معتبر به نظر
     * می‌رسد ــ چیزی که در اجرای واقعی رخ نمی‌دهد.
     */
    private function freshRequestCycle(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function login(User $user): \Illuminate\Testing\TestResponse
    {
        $this->freshRequestCycle();

        return $this->postJson('/api/login', array_merge([
            'email' => $user->email,
            'password' => self::PASSWORD,
        ], $this->captcha()));
    }

    private function makeUser(string $state = 'approved'): User
    {
        $factory = User::factory()->state(['password' => Hash::make(self::PASSWORD)]);

        return match ($state) {
            'unverified' => $factory->unverified()->create(['approved_at' => null]),
            'pending' => $factory->pendingApproval()->create(),
            'rejected' => $factory->rejected()->create(),
            default => $factory->create(),
        };
    }

    public function test_approved_user_can_login(): void
    {
        $response = $this->login($this->makeUser());

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_user_pending_approval_cannot_login(): void
    {
        $response = $this->login($this->makeUser('pending'));

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'حساب شما در انتظار تأیید مدیر است.');
        $this->assertNull($response->json('data.token'));
    }

    public function test_rejected_user_cannot_login(): void
    {
        $response = $this->login($this->makeUser('rejected'));

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'درخواست عضویت شما پذیرفته نشد.');
    }

    public function test_unverified_user_cannot_login(): void
    {
        $response = $this->login($this->makeUser('unverified'));

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'ابتدا ایمیل خود را تأیید کنید.');
    }

    public function test_wrong_password_is_rejected_before_status_is_revealed(): void
    {
        $user = $this->makeUser('pending');

        $response = $this->postJson('/api/login', array_merge([
            'email' => $user->email,
            'password' => 'wrong-password',
        ], $this->captcha()));

        // ۴۰۱ عمومی، نه ۴۰۳ که وضعیت حساب را لو بدهد.
        $response->assertStatus(401);
    }

    public function test_verifying_otp_notifies_admins_and_issues_no_token(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'user']);

        $user = User::factory()->unverified()->create([
            'approved_at' => null,
            'verification_code' => Hash::make('123456'),
            'verification_code_expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->postJson('/api/verify-otp', [
            'user_id' => $user->id,
            'otp' => '123456',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending_approval');
        $this->assertNull($response->json('data.token'), 'کاربر تأییدنشده نباید توکن بگیرد');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->isPendingApproval());

        Notification::assertSentTo($admin, NewUserAwaitingApproval::class);
        Notification::assertSentTimes(NewUserAwaitingApproval::class, 1);
    }

    public function test_otp_guessing_is_capped_and_burns_the_code(): void
    {
        $user = User::factory()->unverified()->create([
            'approved_at' => null,
            'verification_code' => Hash::make('123456'),
            'verification_code_expires_at' => now()->addMinutes(2),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/verify-otp', [
                'user_id' => $user->id,
                'otp' => '000000',
            ])->assertStatus(422);
        }

        // کد سوخته است، پس حتی حدس درست هم دیگر کار نمی‌کند.
        $this->assertNull($user->fresh()->verification_code);

        $this->postJson('/api/verify-otp', [
            'user_id' => $user->id,
            'otp' => '123456',
        ])->assertStatus(429);
    }

    public function test_resend_otp_enforces_a_server_side_cooldown(): void
    {
        $user = User::factory()->unverified()->create(['approved_at' => null]);

        $this->postJson('/api/resend-otp', ['user_id' => $user->id])->assertOk();

        $second = $this->postJson('/api/resend-otp', ['user_id' => $user->id]);
        $second->assertStatus(429);
        $second->assertHeader('Retry-After');
    }

    public function test_admin_can_approve_a_pending_user_who_then_logs_in(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $user = $this->makeUser('pending');

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$user->id}/approve")
            ->assertOk();

        $user->refresh();
        $this->assertTrue($user->isApproved());
        $this->assertSame($admin->id, $user->approved_by);

        Notification::assertSentTo($user, AccountApprovalDecided::class);

        $this->login($user)->assertOk();
    }

    public function test_rejecting_a_user_blocks_login_and_kills_existing_tokens(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $user = $this->makeUser();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$user->id}/reject")
            ->assertOk();

        $this->assertTrue($user->fresh()->isRejected());
        $this->assertSame(0, $user->tokens()->count());

        $this->freshRequestCycle();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/goals')
            ->assertStatus(401);

        $this->login($user)->assertStatus(403);
    }

    public function test_admin_cannot_reject_another_admin_or_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$admin->id}/reject")
            ->assertStatus(422);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$other->id}/reject")
            ->assertStatus(422);
    }

    public function test_regular_user_cannot_approve_anyone(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $pending = $this->makeUser('pending');

        $this->actingAs($user)
            ->postJson("/api/admin/users/{$pending->id}/approve")
            ->assertStatus(403);

        $this->assertTrue($pending->fresh()->isPendingApproval());
    }

    public function test_admin_list_can_be_filtered_by_pending_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pending = $this->makeUser('pending');
        $this->makeUser();

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/users?status=pending')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertSame([$pending->id], $ids);
    }

    public function test_admin_user_list_does_not_leak_the_verification_code_hash(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeUser('pending')->forceFill([
            'verification_code' => Hash::make('123456'),
        ])->save();

        $this->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonMissing(['verification_code']);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = $this->makeUser();
        $phone = $user->createToken('api-token')->plainTextToken;
        $laptop = $user->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$phone}")
            ->postJson('/api/logout')
            ->assertOk();

        $this->freshRequestCycle();
        $this->withHeader('Authorization', "Bearer {$phone}")
            ->getJson('/api/goals')
            ->assertStatus(401);

        $this->freshRequestCycle();
        $this->withHeader('Authorization', "Bearer {$laptop}")
            ->getJson('/api/goals')
            ->assertOk();
    }
}
