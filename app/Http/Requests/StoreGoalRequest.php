<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\GoalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreGoalRequest extends FormRequest
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
            'type' => ['required', new Enum(GoalType::class)],
            'target_value' => ['required', 'numeric', 'gt:0'],
            'distance_m' => ['nullable', 'integer', 'gt:0', Rule::requiredIf($this->input('type') === GoalType::RaceTime->value)],
            'target_date' => ['required', 'date', 'after:today'],
        ];
    }
}
