<?php

namespace Database\Seeders;

use App\Enums\AiPromptCategory;
use App\Models\AiPromptTemplate;
use Illuminate\Database\Seeder;

class AiPromptSeeder extends Seeder
{
    public function run(): void
    {
        $prompts = [
            [
                'key' => 'caption.product',
                'name' => 'Product caption',
                'category' => AiPromptCategory::Caption,
                'description' => 'Short promotional caption for product posts.',
                'template' => 'Write a social caption for {{product}} targeting {{audience}}. Tone: {{tone}}. Max {{max_chars}} characters.',
                'variables' => ['product', 'audience', 'tone', 'max_chars'],
            ],
            [
                'key' => 'content.weekly_plan',
                'name' => 'Weekly content plan',
                'category' => AiPromptCategory::Content,
                'description' => 'Generate a week of post ideas.',
                'template' => 'Create {{count}} post ideas for {{brand}} on {{platforms}} for the week of {{week_start}}.',
                'variables' => ['count', 'brand', 'platforms', 'week_start'],
            ],
            [
                'key' => 'reply.dm',
                'name' => 'DM reply suggestion',
                'category' => AiPromptCategory::Reply,
                'description' => 'Suggest on-brand DM replies.',
                'template' => 'Suggest a reply to this DM in {{tone}}: "{{message}}"',
                'variables' => ['tone', 'message'],
            ],
        ];

        foreach ($prompts as $prompt) {
            AiPromptTemplate::updateOrCreate(
                ['key' => $prompt['key']],
                array_merge($prompt, ['is_active' => true]),
            );
        }
    }
}
