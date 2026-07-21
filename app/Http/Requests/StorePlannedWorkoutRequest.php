<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Sport;
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
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'duration_min' => ['nullable', 'integer', 'between:1,600'],
        ];
    }
}
