<?php

namespace App\Http\Controllers\Api;

use App\Actions\Participants\CompleteParticipantWizard;
use App\Actions\Participants\SaveParticipantWizardStep;
use App\Actions\Participants\UpdateParticipant;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveParticipantWizardStepRequest;
use App\Http\Requests\StoreParticipantRequest;
use App\Http\Requests\UpdateParticipantRequest;
use App\Http\Requests\UploadPhotoRequest;
use App\Http\Resources\ParticipantResource;
use App\Http\Resources\ParticipantWizardResource;
use App\Models\Participant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class ParticipantController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ParticipantResource::collection(
            request()->user()->participants()->latest('id')->get(),
        );
    }

    public function store(StoreParticipantRequest $request): ParticipantResource
    {
        $data = $request->validated();
        $participant = request()->user()->participants()->create([
            'name' => $data['name'],
            'slug' => str($data['name'])->slug()->toString(),
            'birth_date' => $data['birthDate'],
            'gender' => $data['gender'],
            'data_completed' => false,
            'health' => [],
            'emergency_contacts' => [],
            'pickup_contact' => null,
            'medical_insurance' => [],
            'authorizations' => [],
            'wizard_steps' => [],
        ]);

        return new ParticipantResource($participant);
    }

    public function show(int $participant): ParticipantResource
    {
        return new ParticipantResource($this->ownedParticipant($participant));
    }

    public function update(UpdateParticipantRequest $request, int $participant, UpdateParticipant $action): ParticipantResource
    {
        $data = $request->validated();
        $mapped = [];
        foreach ($data as $key => $value) {
            $mapped[
                match ($key) {
                    'birthDate' => 'birth_date',
                    'photoUrl' => 'photo_url',
                    'shirtSize' => 'shirt_size',
                    'weightKg' => 'weight_kg',
                    'emergencyContacts' => 'emergency_contacts',
                    'pickupContact' => 'pickup_contact',
                    'medicalInsurance' => 'medical_insurance',
                    default => $key,
                }
            ] = $value;
        }

        return new ParticipantResource($action->handle($this->ownedParticipant($participant), $mapped));
    }

    public function wizard(int $participant): ParticipantWizardResource
    {
        return new ParticipantWizardResource($this->ownedParticipant($participant));
    }

    public function saveWizardStep(SaveParticipantWizardStepRequest $request, int $participant, SaveParticipantWizardStep $action): ParticipantWizardResource
    {
        $model = $action->handle(
            $this->ownedParticipant($participant),
            $request->string('stepId')->toString(),
            $request->array('data'),
        );

        return new ParticipantWizardResource($model);
    }

    public function completeWizard(int $participant, CompleteParticipantWizard $action): ParticipantResource
    {
        return new ParticipantResource($action->handle($this->ownedParticipant($participant)));
    }

    public function updatePhoto(UploadPhotoRequest $request, int $participant): ParticipantResource
    {
        $model = $this->ownedParticipant($participant);
        if ($model->photo_url) {
            Storage::disk('public')->delete($model->photo_url);
        }
        $path = $request->file('foto')->store('participants', 'public');
        $model->update(['photo_url' => $path]);

        return new ParticipantResource($model->refresh());
    }

    private function ownedParticipant(int $participant): Participant
    {
        return request()->user()->participants()->findOrFail($participant);
    }
}
