<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr; 

class CourseController extends Controller
{
    /**
     * واکشی داده‌های یک دوره بر اساس slug از فایل JSON.
     * مسیر: GET /api/admin/course/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $filePath = "courses/{$slug}.json";

        if (!Storage::exists($filePath)) {
            return response()->json([
                'message' => 'Course not found.',
                'errors' => ["The course file {$slug}.json could not be found."]
            ], 404);
        }

        try {
            $jsonContent = Storage::get($filePath);
            $courseData = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON format in course file.');
            }

            return response()->json([
                'message' => 'Course data loaded successfully.',
                'data' => $courseData, 
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server error processing course data.',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    public function listCourses(): JsonResponse
    {
        $courseList = [];
        $files = Storage::files('courses'); 
        foreach ($files as $filePath) {
            // فقط فایل‌های JSON را پردازش کن
            if (!str_ends_with($filePath, '.json')) {
                continue;
            }

            try {
                $jsonContent = Storage::get($filePath);
                $data = json_decode($jsonContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue; // فایل JSON خراب است
                }

                // استخراج metadata مورد نیاز فرانت‌اند (CourseList.vue)
                $slug = Arr::get($data, 'slug');
                $title = Arr::get($data, 'title', 'Untitled Course');
                
                // بررسی وجود مراحل بصری (steps) برای تعیین visual: true
                $hasVisualSteps = Arr::get($data, 'steps') && count(Arr::get($data, 'steps')) > 0;
                
                // استخراج توضیحات (Description)
                $description = Arr::get($data, 'description');
                if (!$description && Arr::get($data, 'content')) {
                    // اگر فیلد description صریحاً تعریف نشده بود، از اولین پاراگراف محتوا استفاده کن
                    $firstParagraph = Arr::first(Arr::get($data, 'content'), function($item) {
                        return Arr::get($item, 'type') === 'paragraph';
                    });
                    $description = Arr::get($firstParagraph, 'text', 'No description available.');
                } elseif (!$description) {
                     $description = 'No description available.';
                }
                
                // جلوگیری از لیست شدن دوره‌هایی که slug ندارند
                if (!$slug) continue;


                $courseList[] = [
                    'slug' => $slug,
                    'title' => $title,
                    'description' => $description,
                    'visual' => $hasVisualSteps,
                ];

            } catch (\Exception $e) {
                // اگر یک فایل مشکل داشت، باقی فایل‌ها پردازش شوند
                continue; 
            }
        }

        // برگرداندن فهرست نهایی به فرانت‌اند
        return response()->json([
            'message' => 'Course list loaded successfully.',
            'data' => $courseList,
        ], 200);
    }
}