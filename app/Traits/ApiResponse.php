<?php

namespace App\Traits;

use App\Models\DailyLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected $fetched = "The record(s) fetched successfully";
    protected $updated = "The record(s) updated successfully";
    protected $deleted = "The record(s) deleted successfully";

    public function successResponse($data, $message = "The record(s) created successfully", $code = 201): JsonResponse
    {
        return response()->json(
            [
                "success" => true,
                "message" => $message,
                "result" => $data,
                "errors" => [],
            ], $code
        );
    }

    protected function errorResponse($errors, $message = "There was a problem performing the operation", $code = 403): JsonResponse
    {
        return response()->json(
            [
                "success" => false,
                "message" => $message,
                "result" => [],
                "errors" => $errors,
            ], $code
        );
    }

    public static function customThirdPartyResponse($success = true, $code = 201, $message = null, $result = null, $errors = null, $execute_time = 0): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'result' => $result,
            'errors' => $errors,
            'execute_time' => $execute_time,
            'timestamp' => Carbon::now()->timestamp,
        ], $code);
    }

}
