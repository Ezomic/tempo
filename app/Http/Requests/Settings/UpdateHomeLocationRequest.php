<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHomeLocationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'home_lat' => ['required', 'numeric', 'between:-90,90'],
            'home_lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
