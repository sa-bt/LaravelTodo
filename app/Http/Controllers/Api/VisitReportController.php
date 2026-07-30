<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VisitReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The admin side of visit statistics.
 *
 * Admin authorization is enforced by the route group, not here.
 */
class VisitReportController extends Controller
{
    public function __construct(private VisitReportService $reports) {}

    /**
     * One day, hour by hour, against the day before it.
     */
    public function daily(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = $this->parseDate($validated['date'] ?? null);

        if ($date === null) {
            return $this->errorResponse(['Invalid date.'], 'validation_error', 422);
        }

        return $this->successResponse($this->reports->dayReport($date));
    }

    /**
     * The jalali week holding the given day, against the week before it.
     */
    public function weekly(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = $this->parseDate($validated['date'] ?? null);

        if ($date === null) {
            return $this->errorResponse(['Invalid date.'], 'validation_error', 422);
        }

        return $this->successResponse($this->reports->weekReport($date));
    }

    /**
     * A window the admin picked, against the window of the same length before it.
     */
    public function overview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        $from = $this->parseDate($validated['from']);
        $to = $this->parseDate($validated['to']);

        if ($from === null || $to === null) {
            return $this->errorResponse(['Invalid date.'], 'validation_error', 422);
        }

        if ($from->gt($to)) {
            return $this->errorResponse(
                ['Start date must not be after the end date.'],
                'validation_error',
                422
            );
        }

        if ($this->reports->rangeLength($from, $to) > VisitReportService::MAX_RANGE_DAYS) {
            return $this->errorResponse(
                ['Range is longer than '.VisitReportService::MAX_RANGE_DAYS.' days.'],
                'validation_error',
                422
            );
        }

        return $this->successResponse($this->reports->rangeReport($from, $to));
    }

    /**
     * An absent date means today. A date the parser cannot read returns null so
     * the caller answers with a validation error instead of a stack trace.
     */
    private function parseDate(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return Carbon::today();
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
