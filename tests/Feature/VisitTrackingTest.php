<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\User;
use App\Services\VisitTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'visitor_id' => '11111111-2222-3333-4444-555555555555',
            'session_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'path' => '/',
        ], $overrides);
    }

    private function track(array $payload, string $agent = self::BROWSER_AGENT)
    {
        return $this->withHeader('User-Agent', $agent)
            ->postJson('/api/track/view', $payload);
    }

    public function test_a_guest_page_view_is_stored(): void
    {
        $this->track($this->payload(['path' => '/vorod', 'route' => 'login']))
            ->assertNoContent();

        $view = PageView::query()->sole();

        $this->assertSame('/vorod', $view->path);
        $this->assertSame('login', $view->route_name);
        $this->assertTrue($view->is_guest);
        $this->assertNull($view->user_id);
        $this->assertFalse($view->is_bot);
    }

    public function test_a_signed_in_visitor_is_attached_to_the_view(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->track($this->payload(['path' => '/app/day']))
            ->assertNoContent();

        $view = PageView::query()->sole();

        $this->assertSame($user->id, $view->user_id);
        $this->assertFalse($view->is_guest);
    }

    public function test_the_query_string_and_record_ids_are_stripped_from_the_path(): void
    {
        $this->track($this->payload(['path' => '/app/goals/42?tab=open#top']))
            ->assertNoContent();

        $this->assertSame('/app/goals/:id', PageView::query()->sole()->path);
    }

    public function test_a_robot_is_stored_but_flagged(): void
    {
        $this->track($this->payload(), 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
            ->assertNoContent();

        $this->assertTrue(PageView::query()->sole()->is_bot);
        $this->assertSame(0, PageView::query()->human()->count());
    }

    public function test_a_request_without_a_user_agent_counts_as_a_robot(): void
    {
        $this->track($this->payload(), '')->assertNoContent();

        $this->assertTrue(PageView::query()->sole()->is_bot);
    }

    public function test_the_referrer_is_reduced_to_a_host_and_a_group(): void
    {
        $this->track($this->payload(['referrer' => 'https://www.google.com/search?q=todo']))
            ->assertNoContent();

        $view = PageView::query()->sole();

        $this->assertSame('google.com', $view->referrer_host);
        $this->assertSame(PageView::REFERRER_SEARCH, $view->referrer_group);
    }

    public function test_an_absent_referrer_is_a_direct_visit(): void
    {
        $this->track($this->payload())->assertNoContent();

        $view = PageView::query()->sole();

        $this->assertNull($view->referrer_host);
        $this->assertSame(PageView::REFERRER_DIRECT, $view->referrer_group);
    }

    public function test_the_visitor_and_session_ids_are_required(): void
    {
        $this->postJson('/api/track/view', ['path' => '/'])
            ->assertStatus(422);

        $this->assertSame(0, PageView::query()->count());
    }

    public function test_a_malformed_visitor_id_is_rejected(): void
    {
        $this->track($this->payload(['visitor_id' => '<script>alert(1)</script>']))
            ->assertStatus(422);

        $this->assertSame(0, PageView::query()->count());
    }

    public function test_the_device_is_read_from_the_user_agent(): void
    {
        $service = app(VisitTrackingService::class);

        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
        $ipad = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1';

        $this->assertSame(PageView::DEVICE_MOBILE, $service->deviceType($iphone));
        $this->assertSame(PageView::DEVICE_TABLET, $service->deviceType($ipad));
        $this->assertSame(PageView::DEVICE_DESKTOP, $service->deviceType(self::BROWSER_AGENT));
    }

    public function test_a_browser_that_impersonates_another_is_named_correctly(): void
    {
        $service = app(VisitTrackingService::class);

        $edge = 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/120.0 Safari/537.36 Edg/120.0';

        $this->assertSame('edge', $service->browser($edge));
        $this->assertSame('chrome', $service->browser(self::BROWSER_AGENT));
    }

    public function test_a_path_that_is_a_full_address_keeps_only_its_path_part(): void
    {
        $service = app(VisitTrackingService::class);

        $this->assertSame('/app/week', $service->normalizePath('https://example.com/app/week?x=1'));
        $this->assertSame('/', $service->normalizePath(''));
    }
}
