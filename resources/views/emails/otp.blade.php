@php
    use Morilog\Jalali\Jalalian;
    use Morilog\Jalali\CalendarUtils;
    
    $styles = $styles ?? []; 
    $user = $user ?? null;
    $otpCode = $otpCode ?? '000000';
    $expiresInMinutes = $expiresInMinutes ?? 2;

    $primary     = $styles['primary'] ?? '#10b981'; 
    $accent      = $styles['accent'] ?? '#14b8a6';
    $text        = $styles['text'] ?? '#0f172a';
    $secondary   = $styles['secondary'] ?? '#475569'; 
    $background  = $styles['background'] ?? '#f8fafc';
    $border      = $styles['border'] ?? '#e2e8f0';

    $today       = Jalalian::now()->format('Y/m/d');
    $todayFa     = CalendarUtils::convertNumbers($today);
    $otpCodeFa   = CalendarUtils::convertNumbers($otpCode); 
    $expiresInFa = CalendarUtils::convertNumbers($expiresInMinutes);
    
@endphp

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>{{ $title ?? 'تأیید ایمیل حساب کاربری' }}</title>
<style>
@import url('https://cdn.fontcdn.ir/Font/Persian/Vazirmatn/Vazirmatn.css');

/* CSSهای عمومی هنوز برای پشتیبانی اولیه لازم هستند */
body, table, td, p, a, div {
    font-family: 'Vazirmatn',Tahoma,Arial,sans-serif !important;
    direction: rtl !important;
}
.container {
  box-shadow: 0 10px 40px rgba(0,0,0,.08);
}
.header { text-align: right !important; }
.content { text-align: right !important; }
.footer { text-align: center !important; }

</style>
</head>
<body dir="rtl" style="margin: 0; padding: 0; background: linear-gradient(180deg,{{ $background }} 0%,#eef2f7 100%);">

<center> 
<table width="100%" cellpadding="0" cellspacing="0" border="0" dir="rtl" style="direction:rtl; text-align:right;">
<tr>
<td align="center">

<div class="container" dir="rtl" style="max-width: 640px; margin: 2rem auto; background: #fff; border: 1px solid {{ $border }}; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,.08);">

<div class="header" style="background: linear-gradient(135deg, {{ $primary }}, {{ $accent }}); color: #fff; padding: 2.5rem 1.5rem 1rem;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="direction:rtl; text-align:center;">
    <tr>
    <td align="center">
        <div class="icon-circle" style="display:inline-flex;align-items:center;justify-content:center; width:76px;height:76px; background:rgba(255,255,255,.15); border-radius:50%; box-shadow:0 4px 12px rgba(255,255,255,.2) inset; margin-bottom:.75rem;">
          <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:38px;height:38px;fill:#463a3a;opacity:.95;">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
        </div>
        <h1 style="margin:0;font-size:1.55rem;font-weight:800;letter-spacing:-.3px; color:#fff; text-align:center; line-height: 1.2; direction:rtl;">تأیید ایمیل و کد یکبارمصرف</h1>
        
    </td>
    </tr>
    </table>
  </div>

  <div class="content" style="padding:2.5rem 2.25rem; text-align:right; direction: rtl; color: {{ $text }};">
    <h2 style="direction:rtl; text-align:right; margin:0 0 1rem;font-weight:700;color:{{ $text }};font-size:1.2rem;">سلام مدیر سامانه 👋</h2>
    <p style="direction:rtl; text-align:right; line-height: 1.9; margin-top: 1rem;">کد تأیید برای تکمیل فرآیند ثبت‌نام شما درخواست شده است. این کد فقط برای **تأیید مالکیت ایمیل** شما استفاده می‌شود.</p>

    <div class="progress-card" style="text-align:center; background:#f9fafb; border:1px solid #e5e7eb; border-radius:18px; padding:1.8rem 1.75rem; margin:1.5rem 0; box-shadow:0 3px 8px rgba(0,0,0,.04);">
        <h2 style="font-size:1.5rem; margin-bottom:1.25rem; direction:rtl; text-align:right; color:{{ $text }};">کد تأیید شما:</h2> 
        
        <div dir="ltr" style="font-size: 2.5rem; font-weight: 800; color: {{ $text }}; letter-spacing: 5px; background: #fff; border: 2px dashed {{ $border }}; border-radius: 12px; padding: 1.5rem 1rem; margin: 0 auto; width: fit-content; display: inline-block;">
            {{ $otpCodeFa }}
        </div>
        <p style="margin-top:1.5rem; color:{{ $secondary }}; direction:rtl; text-align:right;">این کد تا **{{ $expiresInFa }} دقیقه** دیگر منقضی می‌شود.</p>
    </div>

    
    <div style="clear:both;"></div> 
  </div>

  <div class="footer" style="background:#f9fafb; text-align:center; padding:1.6rem 1rem; border-top:1px solid {{ $border }}; font-size:.92rem; color:{{ $secondary }};">
    <strong>{{ config('app.name') }}</strong> — متعهد به حفظ امنیت شما 🔒
  </div>
</div>
</td>
</tr>
</table>
</center>
</body>
</html>