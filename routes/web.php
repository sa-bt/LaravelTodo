<?php

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
Route::get('/', function () {
    $user = User::first(); // یا یک نمونه فیک بسازید
    $code = '789012';
    
    Mail::send(new OtpMail($user, $code, 2));
    
    return response()->json(['message' => 'ایمیل تستی ارسال شد.']);
    return view('welcome');
});
