<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class GenerateContentRequest extends FormRequest
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
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'prompt' => ['nullable', 'string', 'max:1000'],
            'platform' => ['nullable', 'string', 'in:instagram,facebook,tiktok,linkedin'],
            'content_type' => ['nullable', 'string', 'in:post,reel,story,video'],
            'language' => ['nullable', 'string', 'in:de,en'],
            'seo_focus' => ['nullable', 'string', 'max:100'],
        ];
    }
}
