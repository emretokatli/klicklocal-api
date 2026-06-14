<?php

namespace App\Http\Requests\Admin;

use App\Rules\WebsiteUrl;
use Illuminate\Foundation\Http\FormRequest;

class AnalyzeWebsiteAdminRequest extends FormRequest
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
        ];
    }
}
