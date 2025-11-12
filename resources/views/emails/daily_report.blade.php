@php
    use Morilog\Jalali\Jalalian;
    use Morilog\Jalali\CalendarUtils;

    $primary     = '#10b981'; // Sage primary
    $primaryDark = '#047857';
    $accent      = '#14b8a6';
    $text        = '#0f172a';
    $secondary   = '#475569';
    $background  = '#f8fafc';
    $border      = '#e2e8f0';

    $today       = Jalalian::now()->format('Y/m/d');
    $todayFa     = CalendarUtils::convertNumbers($today);
    $percentFa   = isset($percent)   ? CalendarUtils::convertNumbers($percent)   : 0;
    $remainingFa = isset($remaining) ? CalendarUtils::convertNumbers($remaining) : 0;
    $bodyFa = isset($body) ? CalendarUtils::convertNumbers($body, true) : '';

@endphp


<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>{{ $title ?? 'گزارش پیشرفت روزانه' }}</title>
<style>
@import url('https://cdn.fontcdn.ir/Font/Persian/Vazirmatn/Vazirmatn.css');
body {
  margin: 0; padding: 0;
  background: linear-gradient(180deg,#f8fafc 0%,#eef2f7 100%);
  font-family: 'Vazirmatn',Tahoma,Arial,sans-serif;
  color: {{ $text }};
  line-height: 1.9;
}
.container {
  max-width: 640px;
  margin: 2rem auto;
  background: #fff;
  border: 1px solid {{ $border }};
  border-radius: 24px;
  box-shadow: 0 10px 40px rgba(0,0,0,.08);
  overflow: hidden;
}
.header {
  text-align: center;
  padding: 2.5rem 1.5rem 1rem;
  background: linear-gradient(135deg, {{ $primary }}, {{ $accent }});
  color: #fff;
}
.icon-circle {
  display:inline-flex;align-items:center;justify-content:center;
  width:76px;height:76px;
  background:rgba(255,255,255,.15);
  border-radius:50%;
  box-shadow:0 4px 12px rgba(255,255,255,.2) inset;
  margin-bottom:.75rem;
}
.icon-circle svg {
  width:38px;height:38px;fill:#fff;opacity:.95;
}
.header h1 {
  margin:0;font-size:1.55rem;font-weight:800;letter-spacing:-.3px;
}
.content {
  padding:2.5rem 2.25rem;text-align:right;
}
.content h2 {
  margin:0 0 1rem;font-weight:700;color:{{ $text }};font-size:1.2rem;
}
.progress-card {
  background:#f9fafb;
  border:1px solid #e5e7eb;
  border-radius:18px;
  padding:1.8rem 1.75rem;
  margin:1.5rem 0;
  box-shadow:0 3px 8px rgba(0,0,0,.04);
}
.progress-bar-bg {
  background:#e0f2f1;
  border-radius:9999px;
  height:14px;
  margin:1.2rem 0;
  overflow:hidden;
}
.progress-bar {
  height:100%;
  background:linear-gradient(90deg,{{ $primary }},{{ $accent }});
  width:{{ $percent ?? 0 }}%;
  transition:width .4s ease;
}
.stats-grid {
  display:flex;
  flex-wrap:wrap;
  justify-content:space-between;
  margin-top:.75rem;
  font-size:.95rem;
  color:{{ $secondary }};
}
.stats-grid div strong {
  color:{{ $text }};
}
.motiv {
  background:#ecfdf5;
  border-right:5px solid {{ $accent }};
  border-radius:14px;
  padding:1.25rem 1.5rem;
  font-size:1.05rem;
  color:{{ $text }};
  margin-top:1.5rem;
  box-shadow:0 3px 8px rgba(0,0,0,.03);
}
.btn {
  display:inline-block;
  background:linear-gradient(135deg,{{ $primary }},{{ $accent }});
  color:#fff;
  font-weight:600;
  text-decoration:none;
  padding:0.9rem 1.8rem;
  border-radius:9999px;
  margin-top:2.25rem;
  box-shadow:0 4px 12px rgba(16,185,129,.35);
  transition:all .25s ease-in-out;
}
.btn:hover {
  transform:translateY(-1px);
  box-shadow:0 6px 16px rgba(16,185,129,.45);
}
.footer {
  background:#f9fafb;
  text-align:center;
  padding:1.6rem 1rem;
  border-top:1px solid {{ $border }};
  font-size:.92rem;
  color:{{ $secondary }};
}
.footer strong{color:{{ $text }};}
</style>
</head>
<body>
<div class="container">

  <!-- Header -->
  <div class="header">
    <div class="icon-circle">
      <!-- آیکن میله‌ای داخل دایره -->
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
        <rect x="3" y="10" width="4" height="10" rx="1"></rect>
        <rect x="10" y="6" width="4" height="14" rx="1"></rect>
        <rect x="17" y="3" width="4" height="17" rx="1"></rect>
      </svg>
    </div>
    <h1>گزارش پیشرفت روزانه</h1>
  </div>

  <!-- Content -->
  <div class="content">
    <h2>سلام {{ $user->name ?? 'دوست عزیز' }} 👋</h2>
    <p>این خلاصهٔ عملکرد تو در تاریخ <strong>{{ $todayFa }}</strong> است:</p>

    @if(isset($percent))
      <div class="progress-card">
        <div class="progress-bar-bg"><div class="progress-bar"></div></div>
        <div class="stats-grid">
          <div><strong>درصد پیشرفت:</strong> {{ $percentFa }}٪</div>
          <div><strong>تسک‌های باقی‌مانده:</strong> {{ $remainingFa }}</div>
        </div>
      </div>
    @endif

    @if(!empty($body))
      <div class="motiv">💬 {{ $bodyFa }}</div>
    @endif

    <a href="{{ $url ?? url('/tasks') }}" class="btn">مشاهده تسک‌ها</a>
  </div>

  <!-- Footer -->
  <div class="footer">
    هر روز یک قدم به هدفت نزدیک‌تر 🌱<br>
    <strong>{{ config('app.name') }}</strong> — همراهت در مسیر پیشرفت
  </div>
</div>
</body>
</html>
