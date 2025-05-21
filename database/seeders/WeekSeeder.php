<?php

namespace Database\Seeders;

use App\Models\Week;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Database\Seeder;

class WeekSeeder extends Seeder
{
    /**
     * Seed the application's goals table.
     */
    public function run(): void
    {
        $user = User::where(['email' => "sa.bt@chmail.ir"])->first();

        $startDate = Carbon::now()->startOfWeek(); // اولین روز هفته فعلی (شنبه یا دوشنبه بسته به locale)
        for ($i = 0; $i < 52; $i++) {
            $weekStart = $startDate->copy()->addWeeks($i);
            $weekEnd = $weekStart->copy()->addDays(6); // 7 روز بعد
            $record = [
                'user_id' => $user->id,
                'title' => 'هفته ' . ($i + 1),
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'result' => 0,
            ];
            Week::firstOrCreate(["title" => $record["title"]], $record);
            dd(88);
        }
    }
}
