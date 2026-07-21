<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\HrZoneSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHrZoneSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'max_hr' => ['nullable', 'integer', 'between:120,240'],
            'resting_hr' => ['nullable', 'integer', 'between:25,120'],
            'lthr' => ['nullable', 'integer', 'between:80,220'],
            'sex' => ['required', Rule::in([HrZoneSettings::SEX_MALE, HrZoneSettings::SEX_FEMALE])],
        ];
    }
}
