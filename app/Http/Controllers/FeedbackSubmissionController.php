<?php

namespace App\Http\Controllers;

use App\Models\FeedbackSubmission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedbackSubmissionController extends Controller
{
    // Deliberately not behind auth:sanctum — the widget that posts here is
    // open to logged-out landing visitors too. `sanctum` guard resolution
    // still works without the middleware, so a logged-in student's
    // submission gets attributed automatically.
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['suggestion', 'review'])],
            'message' => ['required', 'string', 'max:2000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $submission = FeedbackSubmission::create([
            'user_id' => $request->user('sanctum')?->id,
            'type' => $validated['type'],
            'rating' => $validated['type'] === 'review' ? ($validated['rating'] ?? null) : null,
            'message' => $validated['message'],
        ]);

        return response()->json($submission, 201);
    }

    // Super admin only (see routes/api.php) — every suggestion/review, newest
    // first, with the submitter attached where one was logged in.
    public function index(Request $request)
    {
        $query = FeedbackSubmission::with('user')->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return response()->json($query->get());
    }
}
