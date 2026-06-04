<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class PublishPostRequest extends FormRequest
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
            'social_account_ids' => ['sometimes', 'array'],
            'social_account_ids.*' => ['integer', 'exists:social_accounts,id'],
        ];
    }
}
