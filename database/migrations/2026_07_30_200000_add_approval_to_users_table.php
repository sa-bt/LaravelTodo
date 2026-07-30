<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * سه حالت برای هر حساب: در انتظار (هر دو ستون خالی)، تأییدشده و ردشده.
     *
     * حساب‌های موجود عمداً تأییدشده جا زده می‌شوند. اگر خالی می‌ماندند، با
     * اولین استقرار همهٔ کاربران فعلی پشت دروازهٔ تأیید قفل می‌شدند و صف
     * انتظار ادمین از روز اول پر می‌شد.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('email_verified_at');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->foreignId('approved_by')->nullable()->after('rejected_at')
                ->constrained('users')->nullOnDelete();
        });

        DB::table('users')->update(['approved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'rejected_at']);
        });
    }
};
