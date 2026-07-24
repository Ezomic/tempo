<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Intensity;
use App\Enums\Sport;
use App\Enums\WorkoutType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlannedWorkoutRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'sport' => ['required', Rule::enum(Sport::class)],
            'workout_type' => ['nullable', Rule::enum(WorkoutType::class)],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'duration_min' => ['nullable', 'integer', 'between:1,600'],

            'steps' => ['nullable', 'array', 'max:40'],
            'steps.*.repeat' => ['required_with:steps', 'integer', 'between:1,50'],
            'steps.*.intensity' => ['required_with:steps', Rule::enum(Intensity::class)],
            'steps.*.duration_min' => ['required_with:steps', 'integer', 'between:1,600'],
            'steps.*.recovery_min' => ['nullable', 'integer', 'between:0,120'],
            'steps.*.recovery_intensity' => ['nullable', Rule::enum(Intensity::class)],
            'steps.*.label' => ['nullable', 'string', 'max:100'],
        ];
    }
}
