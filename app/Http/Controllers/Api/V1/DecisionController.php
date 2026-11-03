<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PreferredLanguage;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\DecisionResponse;
use App\Models\LessonPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Records an answer to a decision page ("Did you believe in Jesus?").
 * Open to visitors: they are known by a random id kept on their phone.
 * Answering again changes the earlier answer instead of adding one.
 */
class DecisionController extends Controller
{
    public function store(Request $request, LessonPage $page): JsonResponse
    {
        abort_unless($page->isDecision(), 404);

        $data = $request->validate([
            'choice_index' => ['required', 'integer', 'min:0', 'max:9'],
            'device_id' => ['nullable', 'string', 'max:64'],
            'lang' => ['nullable', 'string', 'max:8'],
        ]);
        $user = $request->user('sanctum');
        $device = $data['device_id'] ?? null;
        if ($user === null && $device === null) {
            return ApiResponse::error('A device id is required without an account.', [], 422);
        }

        // A visitor who signs up later keeps one answer, now on their account.
        $response = DecisionResponse::query()
            ->where('lesson_page_id', $page->id)
            ->where(function ($q) use ($user, $device) {
                if ($user !== null) {
                    $q->where('user_id', $user->id);
                }
                if ($device !== null) {
                    $q->orWhere(fn ($d) => $d->where('device_id', $device)->whereNull('user_id'));
                }
            })
            ->first() ?? new DecisionResponse(['lesson_page_id' => $page->id]);

        $response->fill([
            'user_id' => $user?->id ?? $response->user_id,
            'device_id' => $device ?? $response->device_id,
            'choice_index' => (int) $data['choice_index'],
            'language' => PreferredLanguage::resolve($data['lang'] ?? null)->value,
        ])->save();

        return ApiResponse::success(['choice_index' => $response->choice_index], status: 201);
    }
}
