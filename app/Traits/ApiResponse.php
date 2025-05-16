<?php

namespace App\Traits;


trait ApiResponse
{
    protected function successResponse($data = null, $message = 'OK', $code = 200)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    protected function errorResponse($message = 'Something went wrong', $code = 400)
    {
        return response()->json([
            'status' => false,
            'message' => $message
        ], $code);
    }
}
