<?php

namespace Database\Factories;

use App\Models\Participant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Participant> */
class ParticipantFactory extends Factory
{
    protected $model = Participant::class;

    public function definition(): array
    {
        return [
            'user_uuid' => User::factory()->create()->uuid,
            'name' => fake()->name(),
            'birth_date' => fake()->date(),
            'gender' => fake()->randomElement(['MASCULINO', 'FEMENINO', 'OTRO', 'PREFIERO_NO_DECIR']),
            'identification' => null,
            'photo_url' => null,
            'data_completed' => false,
            'health' => [],
            'emergency_contacts' => [],
            'pickup_contact' => null,
            'medical_insurance' => [],
            'authorizations' => [],
        ];
    }
}
