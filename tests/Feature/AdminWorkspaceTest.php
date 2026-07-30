<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Goal;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_users_cannot_access_admin_workspace_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/admin/dashboard')->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
        $this->getJson('/api/admin/contacts')->assertForbidden();
    }

    public function test_dashboard_and_user_list_return_real_aggregates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        $goal = Goal::query()->create([
            'user_id' => $member->id,
            'title' => 'Test goal',
            'priority' => 'medium',
            'status' => 'in_progress',
        ]);
        Task::query()->create([
            'goal_id' => $goal->id,
            'title' => 'Test task',
            'day' => now()->toDateString(),
            'is_done' => true,
        ]);
        Contact::query()->create([
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'message' => 'Hello',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.users.total', 2)
            ->assertJsonPath('data.activity.goals', 1)
            ->assertJsonPath('data.activity.completed_today', 1)
            ->assertJsonPath('data.contacts.new', 1);

        $this->getJson('/api/admin/users?search='.$member->email)
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $member->id)
            ->assertJsonPath('data.data.0.goals_count', 1)
            ->assertJsonPath('data.data.0.tasks_count', 1);
    }

    public function test_contact_workflow_can_be_updated_by_an_administrator(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $contact = Contact::query()->create([
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'message' => 'Please contact me.',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/contacts/{$contact->id}", [
            'status' => 'answered',
            'admin_note' => 'Answered by email.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'answered')
            ->assertJsonPath('data.handler.id', $admin->id);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'status' => 'answered',
            'handled_by' => $admin->id,
        ]);
    }

    public function test_administrator_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$admin->id}/role", ['role' => 'user'])
            ->assertUnprocessable();

        $this->assertSame('admin', $admin->fresh()->role);
    }
}
