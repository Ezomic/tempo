<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class CompleteGarminMfaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login_token' => ['required', 'string'],
            'code' => ['required', 'string'],
        ];
    }
}
