<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Intensity;
use App\Enums\Sport;
use App\Enums\WorkoutType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreWorkoutTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'sport' => ['required', new Enum(Sport::class)],
            'workout_type' => ['nullable', new Enum(WorkoutType::class)],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.repeat' => ['required', 'integer', 'min:1', 'max:50'],
            'steps.*.intensity' => ['required', new Enum(Intensity::class)],
            'steps.*.duration_min' => ['required', 'integer', 'min:1', 'max:600'],
            'steps.*.recovery_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'steps.*.recovery_intensity' => ['nullable', new Enum(Intensity::class)],
            'steps.*.label' => ['nullable', 'string', 'max:60'],
        ];
    }
}
