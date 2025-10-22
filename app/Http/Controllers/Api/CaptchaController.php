<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CaptchaController extends Controller
{
    use ApiResponse;

    private int $len = 5;
    private int $ttlSeconds = 300; // 5 دقیقه

    public function new(Request $request)
    {
        $phrase = $this->generatePhrase($this->len);
        $id     = bin2hex(random_bytes(16));
        $hash   = $this->hashPhrase($phrase);

        Cache::put($this->key($id), $hash, now()->addSeconds($this->ttlSeconds));

        $svg        = $this->makeSvgStrong($phrase);
        $base64     = base64_encode($svg);
        $dataUrlB64 = 'data:image/svg+xml;base64,' . $base64;
        $dataUrlUtf = 'data:image/svg+xml;utf8,' . rawurlencode($svg);

        return response()->json([
            'status' => true,
            'message'=> __('success'),
            'data'   => [
                'id'            => $id,
                'image'         => $dataUrlB64, // پیش‌فرض فرانت
                'image_utf8'    => $dataUrlUtf, // آلترناتیو
                'ttl'           => $this->ttlSeconds,
                'type'          => 'svg',
                'width'         => 240,
                'height'        => 72,
            ],
        ], 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'id'     => 'required|string|size:32',
            'answer' => 'required|string|max:16'
        ]);

        $id     = $request->input('id');
        $answer = $this->normalizeAnswer($request->input('answer'));

        $key    = $this->key($id);
        $stored = Cache::pull($key);

        if (!$stored) {
            return $this->errorResponse(
                errors: ['captcha' => ['کپچا منقضی شده، دوباره تلاش کنید.']],
                messageKey: 'کپچا منقضی شده، دوباره تلاش کنید.',
                code: Response::HTTP_GONE
            );
        }

        $ok = hash_equals($stored, $this->hashPhrase($answer));
        if (!$ok) {
            return $this->errorResponse(
                errors: ['captcha' => ['کد تأیید اشتباه است.']],
                messageKey: 'کد تأیید اشتباه است.',
                code: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->successResponse(['ok' => true, 'verified' => true], 'success', 200);
    }

    private function key(string $id): string { return "captcha:$id"; }

    private function generatePhrase(int $len): string
    {
        // حذف کاراکترهای اشتباه‌زا
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $s = '';
        for ($i=0; $i<$len; $i++) $s .= $chars[random_int(0, strlen($chars)-1)];
        return $s;
    }

    private function normalizeAnswer(string $v): string
    {
        $map = ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'];
        $v = trim($v);
        $v = preg_replace('/\s+/u','',$v) ?? $v;
        $v = strtr($v, $map);
        return strtoupper($v);
    }

    private function hashPhrase(string $phrase): string
    {
        $pepper = (string) config('app.captcha_pepper', '');
        if ($pepper === '') abort(500, 'CAPTCHA pepper not configured');
        return hash('sha256', strtoupper($phrase) . $pepper);
    }

    /**
     * کپچای قوی‌تر با اعوجاج واقعی + نویز
     */
    private function makeSvgStrong(string $text): string
    {
        $text = strtoupper($text);

        // بوم
        $w = 240; $h = 72;

        // پارامترهای اعوجاج
        $seed  = random_int(1, 9999);
        $freq  = [0.008, 0.012, 0.016][random_int(0,2)];
        $scale = random_int(6, 10);     // شدت اعوجاج
        $skew  = random_int(-6, 6);     // درجهٔ کج‌نمایی حول مرکز

        // تنظیمات چیدمان حروف
        $len = strlen($text);
        $letterSpacing = 30;            // فاصلهٔ پایهٔ هر کاراکتر
        // عرض تقریبی کل متن (len-1 فاصله داریم چون هر کاراکتر حول مرکز خودش است)
        $textApproxWidth = max(0, ($len - 1) * $letterSpacing);
        // نقطهٔ شروع افقی به‌گونه‌ای که کل متن وسط بوم قرار بگیرد:
        $startX = ($w - $textApproxWidth) / 2;
        $baseY  = (int)($h / 2) + 2;    // تقریباً وسط عمودی

        // تولید SVG حروف با جابه‌جایی و دوران ملایم
        $charsSvg = '';
        for ($i=0; $i<$len; $i++) {
            $ch = htmlspecialchars($text[$i], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $x  = $startX + $i * $letterSpacing + random_int(-2, 2);
            $y  = $baseY + random_int(-4, 4);
            $rot= random_int(-12, 12);
            $fs = random_int(28, 36);
            $wgt= (random_int(0,1) ? 700 : 800);

            $charsSvg .= '<g transform="translate('.$x.','.$y.') rotate('.$rot.')">'
                . '<text text-anchor="middle" dominant-baseline="middle" '
                . 'font-family="ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto" '
                . 'font-size="'.$fs.'" font-weight="'.$wgt.'" letter-spacing="1.2" fill="#0f172a">'
                . $ch . '</text></g>';
        }

        // خطوط مزاحم
        $lines = '';
        for ($i=0; $i<2; $i++) {
            $y1 = random_int(10, $h-10);
            $y2 = random_int(10, $h-10);
            $lines .= '<path d="M 8 '.$y1.' C '.($w*0.35).' '.($y1+random_int(-8,8)).', '
                . ($w*0.65).' '.($y2+random_int(-8,8)).', '.($w-8).' '.$y2.'" '
                . 'stroke="#64748b" stroke-width="1.2" opacity="0.35" fill="none"/>';
        }

        // نقاط نویز
        $dots = '';
        for ($i=0; $i<14; $i++) {
            $dx = random_int(6, $w-6);
            $dy = random_int(6, $h-6);
            $r  = random_int(1, 2);
            $dots .= '<circle cx="'.$dx.'" cy="'.$dy.'" r="'.$r.'" fill="#94a3b8" opacity="0.25"/>';
        }

        // فیلتر اعوجاج
        $svg = <<<SVG
<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}">
  <defs>
    <linearGradient id="bg" x1="0" x2="1" y1="0" y2="1">
      <stop offset="0%" stop-color="#f3f6fb"/>
      <stop offset="100%" stop-color="#e8eef7"/>
    </linearGradient>
    <filter id="distort" x="-20%" y="-20%" width="140%" height="140%">
      <feTurbulence type="turbulence" baseFrequency="{$freq}" numOctaves="2" seed="{$seed}" result="noise"/>
      <feDisplacementMap in="SourceGraphic" in2="noise" scale="{$scale}" xChannelSelector="R" yChannelSelector="G"/>
    </filter>
  </defs>

  <!-- پس‌زمینه -->
  <rect width="100%" height="100%" fill="url(#bg)"/>

  <!-- گروه مرکزی: skew حول مرکز بوم -->
  <g transform="translate({$w}/2,{$h}/2) skewY({$skew}) translate(-{$w}/2,-{$h}/2)" filter="url(#distort)">
    <!-- خطوط و نقطه‌ها -->
    {$lines}
    {$dots}
    <!-- کاراکترها (از پیش مرکز شده با startX) -->
    {$charsSvg}
  </g>
</svg>
SVG;

        return $svg;
    }
}
