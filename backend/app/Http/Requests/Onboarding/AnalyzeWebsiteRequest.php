<?php

namespace App\Http\Requests\Onboarding;

use App\Rules\WebsiteUrl;
use Illuminate\Foundation\Http\FormRequest;

class AnalyzeWebsiteRequest extends FormRequest
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
            'website' => ['required', 'string', 'max:500', new WebsiteUrl],
            'business_name' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:120'],
        ];
    }
}
