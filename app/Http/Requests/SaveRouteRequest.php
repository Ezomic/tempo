<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRouteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'coordinates' => ['required', 'array', 'min:2', 'max:2000'],
            'coordinates.*' => ['array', 'size:2'],
            'coordinates.*.0' => ['numeric', 'between:-90,90'],
            'coordinates.*.1' => ['numeric', 'between:-180,180'],
            'distance_m' => ['required', 'integer', 'min:0'],
            'ascent_m' => ['required', 'integer', 'min:0'],
            'kind' => ['required', Rule::in(['loop', 'out_and_back'])],
        ];
    }
}
