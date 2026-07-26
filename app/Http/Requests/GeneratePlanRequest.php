<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Sport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePlanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'race_date' => ['required', 'date', 'after:today'],
            'sport' => ['required', Rule::enum(Sport::class)],
            'sessions_per_week' => ['required', 'integer', 'between:3,6'],
        ];
    }
}
