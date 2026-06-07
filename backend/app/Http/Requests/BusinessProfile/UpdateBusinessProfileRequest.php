<?php

namespace App\Http\Requests\BusinessProfile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'tone_of_voice' => ['nullable', 'string', 'max:120'],
            'products_services' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
