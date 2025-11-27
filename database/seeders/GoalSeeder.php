<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Goal;
use App\Models\User;
use App\Models\Task;
use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

class GoalSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'bakhshian2020@gmail.com')->first();

        if (!$user) {
            echo "⛔ User with email sa.bt@chmail.ir not found.\n";
            return;
        }

        // --- فاز ۱: اهداف والد و مستقل ---
        $goals = [
            ['title' => 'PHP/Laravel', 'description' => 'تسلط بر مفاهیم لاراول ۱۲ و ساخت پروژه تمرینی.', 'priority' => 'high', 'send_task_reminder' => true, 'reminder_time' => '22:15:00'],
            ['title' => 'Docker', 'description' => 'یادگیری مفاهیم Docker و اجرای پروژه‌ها در محیط کانتینری.', 'priority' => 'high', 'send_task_reminder' => true, 'reminder_time' => '22:45:00'],
            ['title' => 'Git/GitHub', 'description' => 'مرور دستورات گیت و مدیریت پروژه‌ها در GitHub.', 'priority' => 'high'],
            ['title' => 'زبان انگلیسی', 'description' => 'تمرین مکالمه، شنیدار و لغت.', 'priority' => 'high'],
            ['title' => 'لینوکس', 'description' => 'آشنایی با دستورات پایه و تمرین در ترمینال.', 'priority' => 'high', 'send_task_reminder' => true, 'reminder_time' => '22:20:00'],
            ['title' => 'تمرین خط', 'description' => 'تمرین خوش‌خطی روزانه.', 'priority' => 'high', 'send_task_reminder' => true, 'reminder_time' => '22:50:00'],
            ['title' => 'کتاب', 'description' => 'مطالعه روزانه کتاب‌های توسعه فردی.', 'priority' => 'medium', 'send_task_reminder' => true, 'reminder_time' => '22:40:00'],
            ['title' => 'شرکت', 'description' => 'بررسی و انجام وظایف کاری.', 'priority' => 'medium', 'send_task_reminder' => true, 'reminder_time' => '22:00:00'],
            ['title' => 'ورزش', 'description' => 'ورزش روزانه برای حفظ تناسب اندام.', 'priority' => 'high'],
            ['title' => 'Vue.js', 'description' => 'یادگیری Vue.js و ساخت پروژه تمرینی.', 'priority' => 'high', 'send_task_reminder' => true, 'reminder_time' => '22:55:00'],
            ['title' => 'بهداشت فردی', 'description' => 'مراقبت روزانه از بدن و دندان.', 'priority' => 'high'],
        ];

        foreach ($goals as &$g) {
            $g['user_id'] = $user->id;
            $g['status'] = 'pending';
            $g['send_task_reminder'] = $g['send_task_reminder'] ?? false;
            $g['reminder_time'] = $g['reminder_time'] ?? null;
            $g['created_at'] = now();
            $g['updated_at'] = now();
        }
        unset($g);
        Goal::insert($goals);

        // --- فاز ۲: زیرهدف‌ها ---
        $map = Goal::where('user_id', $user->id)->pluck('id', 'title')->toArray();

        $subs = [
            ['title' => 'نصرت', 'description' => 'تمرین شنیداری و گفتاری با مجموعه نصرت.', 'parent' => 'زبان انگلیسی', 'reminder_time' => '22:30:00'],
            ['title' => 'LearnIT', 'description' => 'مطالعه ویدیوهای آموزشی فناوری اطلاعات.', 'parent' => 'زبان انگلیسی', 'reminder_time' => '23:00:00'],
            ['title' => 'لغت', 'description' => 'مرور و حفظ روزانه ۱۰ لغت جدید.', 'parent' => 'زبان انگلیسی', 'reminder_time' => '19:00:00'],
            ['title' => 'دمبل', 'description' => 'تمرینات قدرتی با دمبل.', 'parent' => 'ورزش', 'reminder_time' => '20:00:00'],
            ['title' => 'شنا', 'description' => 'تمرین شنای روزانه.', 'parent' => 'ورزش', 'reminder_time' => '20:30:00'],
            ['title' => 'شکم', 'description' => 'تمرینات شکم و پهلو.', 'parent' => 'ورزش', 'reminder_time' => '21:00:00'],
            ['title' => 'مسواک زدن روزانه', 'description' => 'مسواک زدن بعد از وعده‌های غذایی.', 'parent' => 'بهداشت فردی', 'reminder_time' => '07:30:00'],
            ['title' => 'آب‌نمک‌کشی', 'description' => 'غرغره کردن آب‌نمک برای بهداشت دهان.', 'parent' => 'بهداشت فردی', 'reminder_time' => '20:00:00'],
        ];

        foreach ($subs as &$s) {
            $s['user_id'] = $user->id;
            $s['status'] = 'pending';
            $s['priority'] = 'medium';
            $s['send_task_reminder'] = true;
            $s['parent_id'] = $map[$s['parent']] ?? null;
            unset($s['parent']);
            $s['created_at'] = now();
            $s['updated_at'] = now();
        }
        unset($s);
        Goal::insert($subs);

        // --- فاز ۳: ساخت تسک‌ها برای اهداف فرزند ---
        $goals = Goal::where('user_id', $user->id)->get()->keyBy('title');

        $patterns = [
            'PHP/Laravel' => [6, 1, 2, 4],
            'Docker' => [2, 3],
            'Git/GitHub' => [4, 5],
            'نصرت' => [2, 3],
            'LearnIT' => [0,1,2,3,4,5,6],
            'لغت' => [0,1,2,3,4,5,6],
            'لینوکس' => [4, 5],
            'تمرین خط' => [0,2,4],
            'کتاب' => [0,2,4],
            'شرکت' => [0,1,2,3,4],
            'Vue.js' => [4,5],
            'دمبل' => [0,1,2,3,4],
            'شنا' => [0,1,2,3,4],
            'شکم' => [0,1,2,3,4],
            'مسواک زدن روزانه' => [0,1,2,3,4,5,6],
            'آب‌نمک‌کشی' => [0,3],
        ];

        $today = Carbon::today();
        $end = $today->copy()->addYear();

        foreach ($goals as $goal) {
            $pattern = $patterns[$goal->title] ?? null;
            if (!$pattern) continue;

            // فقط برای اهداف فرزند
            if ($goal->children()->count() > 0) continue;

            $count = 0;
            $date = $today->copy();
            while ($date <= $end) {
                if (in_array($date->dayOfWeek, $pattern)) {
                    $jalali = Jalalian::fromCarbon($date)->format('%A %Y/%m/%d');
                    $title = "تسک روز {$jalali} برای هدف {$goal->title}";

                    Task::updateOrCreate(
                        ['goal_id' => $goal->id, 'day' => $date->toDateString()],
                        ['title' => $title, 'is_done' => false]
                    );
                    $count++;
                }
                $date->addDay();
            }

            echo "📅 {$goal->title} → {$count} تسک ساخته شد.\n";
        }

        echo "✅ ساخت تسک‌ها برای اهداف فرزند تا یک سال آینده انجام شد.\n";
    }
}
