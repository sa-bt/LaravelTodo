<?php

// app/Traits/ApiResponse.php

namespace App\Traits;

trait ApiResponse
{
    public function successResponse($data = null, $messageKey = 'success', $code = 200)
    {
        return response()->json([
            'status'  => true,
            'message' => __($messageKey),
            'data'    => $data
        ], $code);
    }

    public function errorResponse($errors = [],$messageKey = 'error', $code = 400, )
    {
        return response()->json([
            'status'  => false,
            'message' => __($messageKey),
            'errors'  => $errors
        ], $code);
    }
}

