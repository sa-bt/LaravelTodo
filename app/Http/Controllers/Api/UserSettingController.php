<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserSettingController extends Controller
{
    public function getSetting(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'daily_report' => $user->daily_report,
            'report_time' => $user->report_time,
            'task_reminder' => $user->task_reminder,
            'task_reminder_time' => $user->task_reminder_time,
        ]);
    }

    public function saveSetting(Request $request)
    {
        $data = $request->validate([
            'daily_report' => 'required|boolean',
            'report_time' => 'required|date_format:H:i',
            'task_reminder' => 'required|boolean',
            'task_reminder_time' => 'required|date_format:H:i',
        ]);

        $request->user()->update($data);

        return response()->json(['success' => true, 'setting' => $data]);
    }
}
