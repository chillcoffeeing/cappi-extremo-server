<?php

namespace App\Http\Controllers\Api;

use App\Actions\Onboarding\CompleteOnboarding;
use App\Actions\Onboarding\SaveOnboardingStep;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteOnboardingRequest;
use App\Http\Requests\SaveOnboardingStepRequest;
use App\Http\Resources\OnboardingDraftResource;
use App\Models\OnboardingDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    public function complete(CompleteOnboardingRequest $request, string $draftId, CompleteOnboarding $action): JsonResponse
    {
        $draft = $action->handle($this->draft($draftId), $request->file('comprobante'));

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

        // F-059 (api-A-3): a un representante que ya completo su onboarding
        // no se le abre un draft nuevo (con el se inscribian hijos sin orden).
        // Sus drafts existentes se siguen pudiendo leer.
        if (request()->user()->newQuery()->whereKey(request()->user()->getKey())->value('onboarding_status') === 'COMPLETADO') {
            throw ValidationException::withMessages([
                'onboarding' => [CompleteOnboarding::ALREADY_COMPLETED_MESSAGE],
            ]);
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
