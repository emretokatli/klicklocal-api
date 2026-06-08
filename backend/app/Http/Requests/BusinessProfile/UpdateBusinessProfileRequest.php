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
            'city' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'tone_of_voice' => ['nullable', 'string', 'max:120'],
            'products_services' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'string', 'max:500'],
            'team_size' => ['nullable', 'string', 'max:60'],
            'monthly_revenue' => ['nullable', 'string', 'max:60'],
            'customer_source' => ['nullable', 'string', 'max:120'],
            'social_media_channels' => ['nullable', 'array'],
            'social_media_channels.*' => ['string', 'max:60'],
            'target_audience' => ['nullable', 'string', 'max:2000'],
            'unique_value_proposition' => ['nullable', 'string', 'max:2000'],
            'additional_notes' => ['nullable', 'string', 'max:2000'],
            'primary_goal' => ['nullable', 'string', 'max:120'],
        ];
    }
}
