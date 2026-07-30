<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['new', 'reviewing', 'answered', 'archived'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $contacts = Contact::query()
            ->with('handler:id,name')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->successResponse($contacts);
    }

    public function update(Request $request, int $contact): JsonResponse
    {
        $contact = Contact::query()->findOrFail($contact);
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'reviewing', 'answered', 'archived'])],
            'admin_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $contact->update([
            ...$data,
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return $this->successResponse($contact->fresh()->load('handler:id,name'));
    }
}
