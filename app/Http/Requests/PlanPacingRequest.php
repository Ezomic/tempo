<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanPacingRequest extends FormRequest
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
            'gpx' => ['required', 'file', 'max:8192'],
            'target_seconds' => ['required', 'integer', 'gt:0'],
            'split_km' => ['required', 'numeric', Rule::in([0.5, 1, 2, 5])],
        ];
    }
}
