<?php

namespace App\Http\Controllers\Api;

use App\Actions\Onboarding\CompleteOnboarding;
use App\Actions\Onboarding\SaveOnboardingStep;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveOnboardingStepRequest;
use App\Http\Resources\OnboardingDraftResource;
use App\Models\OnboardingDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function show(string $draftId, Request $request): JsonResponse
    {
        return response()->json([
            'data' => (new OnboardingDraftResource($this->draft($draftId)))->resolve($request),
        ]);
    }

    public function saveStep(SaveOnboardingStepRequest $request, string $draftId, SaveOnboardingStep $action): JsonResponse
    {
        $draft = $action->handle(
            $this->draft($draftId),
            $request->string('stepId')->toString(),
            $request->array('data'),
        );

        return response()->json([
            'draft' => (new OnboardingDraftResource($draft))->resolve($request),
            'status' => $draft->status,
        ]);
    }

    public function complete(string $draftId, CompleteOnboarding $action, Request $request): JsonResponse
    {
        $draft = $action->handle($this->draft($draftId));

        return response()->json([
            'draft' => (new OnboardingDraftResource($draft))->resolve($request),
            'status' => $draft->status,
        ]);
    }

    private function draft(string $draftId): OnboardingDraft
    {
        $existing = OnboardingDraft::where('uuid', $draftId)->first();
        abort_if($existing && $existing->user_uuid !== request()->user()->uuid, 404);

        if ($existing) {
            return $existing;
        }

        $draft = request()->user()->onboardingDrafts()->make([
            'version' => 0,
            'completed_steps' => [],
            'data' => [],
            'status' => 'INCOMPLETO',
        ]);
        $draft->uuid = $draftId;
        $draft->save();

        return $draft;
    }
}
