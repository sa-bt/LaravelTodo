<?php

namespace App\Services;

use App\Models\PageView;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Turns one reported page view into one stored row.
 *
 * Everything here is deliberately simple string work. No lookup service, no
 * extra package: a browser hint is a guess either way, and a wrong guess must
 * not be able to break the request that carries it.
 */
class VisitTrackingService
{
    /**
     * Stored paths are capped well under the column width, and the extra room
     * leaves space for the marker below.
     */
    private const MAX_PATH_LENGTH = 180;

    private const TRUNCATION_MARKER = '…';

    /**
     * Anything self reporting as automation. The client side beacon already
     * filters out most crawlers, since a crawler rarely runs the page script,
     * so this only has to catch the ones that do.
     */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|search|fetcher|monitor|headless|phantom|lighthouse|preview|scrape|curl|wget|python-requests|axios|okhttp|libwww|http-client/i';

    private const SEARCH_HOSTS = [
        'google', 'bing', 'yahoo', 'duckduckgo', 'yandex', 'baidu', 'ecosia',
        'brave', 'startpage', 'ask', 'naver', 'neeva', 'parsijoo', 'yooz',
    ];

    private const SOCIAL_HOSTS = [
        'instagram', 'facebook', 'twitter', 'x.com', 't.co', 'linkedin',
        'telegram', 't.me', 'whatsapp', 'youtube', 'reddit', 'pinterest',
        'tiktok', 'aparat', 'virgool', 'medium', 'discord', 'threads',
        'mastodon', 'github',
    ];

    /**
     * Ordered: the first match wins, so the narrow names have to come before
     * the ones they embed. Chrome claims Safari, Edge claims Chrome, and every
     * modern browser claims Mozilla.
     */
    private const BROWSERS = [
        'edge' => '/edg(?:e|a|ios)?\//i',
        'opera' => '/opr\/|opera/i',
        'samsung' => '/samsungbrowser/i',
        'firefox' => '/firefox|fxios/i',
        'chrome' => '/chrome|crios|chromium/i',
        'safari' => '/safari/i',
    ];

    private const PLATFORMS = [
        'android' => '/android/i',
        'ios' => '/iphone|ipad|ipod/i',
        'windows' => '/windows/i',
        'macos' => '/macintosh|mac os x/i',
        'linux' => '/linux|x11/i',
    ];

    /**
     * @param  array<string, mixed>  $payload  the validated request body
     */
    public function record(array $payload, ?string $userAgent, ?User $user): PageView
    {
        $userAgent = (string) $userAgent;
        $isGuest = $user === null;

        return PageView::query()->create([
            'visitor_id' => (string) $payload['visitor_id'],
            'session_id' => (string) $payload['session_id'],
            'user_id' => $user?->id,
            'path' => $this->normalizePath($payload['path'] ?? '/'),
            'route_name' => $this->clean($payload['route'] ?? null, 64),
            'is_guest' => $isGuest,
            'referrer_host' => $this->referrerHost($payload['referrer'] ?? null),
            'referrer_group' => $this->referrerGroup($payload['referrer'] ?? null),
            'utm_source' => $this->clean($payload['utm_source'] ?? null, 100),
            'utm_medium' => $this->clean($payload['utm_medium'] ?? null, 100),
            'utm_campaign' => $this->clean($payload['utm_campaign'] ?? null, 100),
            'device_type' => $this->deviceType($userAgent),
            'browser' => $this->browser($userAgent),
            'platform' => $this->platform($userAgent),
            'is_bot' => $this->isBot($userAgent),
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Query string, fragment and record ids are dropped.
     *
     * Without this the top pages table fills up with one row per goal id and
     * per campaign link, and the page a visitor actually looked at disappears
     * among its own variants.
     */
    public function normalizePath(?string $path): string
    {
        $path = trim((string) $path);

        // A full url may arrive instead of a path; only its path part matters.
        if (Str::startsWith($path, ['http://', 'https://'])) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }

        $path = (string) Str::of($path)->before('?')->before('#');

        if ($path === '' || ! Str::startsWith($path, '/')) {
            $path = '/'.ltrim($path, '/');
        }

        $segments = array_map(
            fn (string $segment): string => $this->normalizeSegment($segment),
            array_filter(explode('/', $path), fn ($segment) => $segment !== '')
        );

        $path = '/'.implode('/', $segments);

        if (mb_strlen($path) > self::MAX_PATH_LENGTH) {
            $path = mb_substr($path, 0, self::MAX_PATH_LENGTH).self::TRUNCATION_MARKER;
        }

        return $path;
    }

    /**
     * A segment that is only a number, or a long hex or uuid looking token, is
     * a record id rather than a page name.
     */
    private function normalizeSegment(string $segment): string
    {
        if (preg_match('/^\d+$/', $segment)) {
            return ':id';
        }

        if (preg_match('/^[0-9a-f-]{16,}$/i', $segment)) {
            return ':id';
        }

        return mb_substr($segment, 0, 60);
    }

    /**
     * Trims an optional free text field to the width of its column.
     *
     * A blank string and a missing value mean the same thing here, so both end
     * up as null and no report has to tell an empty campaign from an absent one.
     */
    private function clean(?string $value, int $length): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }

    public function referrerHost(?string $referrer): ?string
    {
        $host = $this->hostOf($referrer);

        return $host === null ? null : mb_substr($host, 0, 191);
    }

    /**
     * Coarse buckets on purpose. The admin wants to know whether people arrive
     * from a search, from a link somewhere social, or by typing the address.
     */
    public function referrerGroup(?string $referrer): string
    {
        $host = $this->hostOf($referrer);

        if ($host === null) {
            return PageView::REFERRER_DIRECT;
        }

        if ($this->isOwnHost($host)) {
            return PageView::REFERRER_INTERNAL;
        }

        foreach (self::SEARCH_HOSTS as $needle) {
            if (str_contains($host, $needle)) {
                return PageView::REFERRER_SEARCH;
            }
        }

        foreach (self::SOCIAL_HOSTS as $needle) {
            if (str_contains($host, $needle)) {
                return PageView::REFERRER_SOCIAL;
            }
        }

        return PageView::REFERRER_OTHER;
    }

    /**
     * Tablets have to be tested before phones: an Android tablet says android
     * without saying mobile, while an iPad says nothing about being mobile.
     */
    public function deviceType(string $userAgent): string
    {
        if (preg_match('/ipad|tablet|playbook|silk|(android(?!.*mobile))/i', $userAgent)) {
            return PageView::DEVICE_TABLET;
        }

        if (preg_match('/mobile|iphone|ipod|android|blackberry|windows phone|opera mini/i', $userAgent)) {
            return PageView::DEVICE_MOBILE;
        }

        return PageView::DEVICE_DESKTOP;
    }

    public function browser(string $userAgent): string
    {
        foreach (self::BROWSERS as $name => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $name;
            }
        }

        return 'other';
    }

    public function platform(string $userAgent): string
    {
        foreach (self::PLATFORMS as $name => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $name;
            }
        }

        return 'other';
    }

    /**
     * An empty agent counts as a robot. A real browser always sends one, so a
     * missing header means a script.
     */
    public function isBot(string $userAgent): bool
    {
        if (trim($userAgent) === '') {
            return true;
        }

        return (bool) preg_match(self::BOT_PATTERN, $userAgent);
    }

    private function hostOf(?string $referrer): ?string
    {
        $referrer = trim((string) $referrer);

        if ($referrer === '') {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return Str::of($host)->lower()->ltrim('www.')->toString();
    }

    /**
     * A visit that came from our own pages is navigation inside the app, not a
     * new arrival, so it must not be mistaken for a traffic source.
     */
    private function isOwnHost(string $host): bool
    {
        $own = $this->hostOf(config('app.url'));

        return $own !== null && $host === $own;
    }
}
