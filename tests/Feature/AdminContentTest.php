<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/contents', [
            'title' => 'First content',
            'body' => 'Draft body',
            'content_type' => Content::TYPE_NOTE,
            'status' => Content::STATUS_DRAFT,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', 'First content');

        $this->assertDatabaseHas('contents', [
            'title' => 'First content',
            'user_id' => $admin->id,
        ]);
    }

    public function test_regular_user_cannot_access_admin_contents(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/contents')
            ->assertForbidden();
    }

    public function test_admin_can_update_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $content = Content::query()->create([
            'user_id' => $admin->id,
            'title' => 'Old title',
            'body' => 'Old body',
            'content_type' => Content::TYPE_NOTE,
            'status' => Content::STATUS_DRAFT,
        ]);

        $this->assertNotNull($content->id);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/contents/{$content->id}", [
            'title' => 'Updated title',
            'status' => Content::STATUS_READY,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.status', Content::STATUS_READY);

        $this->assertDatabaseHas('contents', [
            'id' => $content->id,
            'title' => 'Updated title',
            'status' => Content::STATUS_READY,
        ]);
    }
}
