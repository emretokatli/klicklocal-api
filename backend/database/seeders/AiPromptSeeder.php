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
            [
                'key' => 'instagram_post_generator',
                'name' => 'Instagram Post Generator',
                'category' => AiPromptCategory::Content,
                'description' => 'Default Instagram feed post system prompt.',
                'template' => "You are an expert German social media copywriter for local businesses.\nWrite for Instagram: engaging, authentic, local. 8-15 hashtags.\nThis is for a standard feed post.\nAlways write in German (Deutsch) unless instructed otherwise.\nRespect the brand's tone of voice. Be authentic, not corporate.\n\nRespond ONLY with a valid JSON object:\n{\n  \"caption\": \"Main post caption with emojis (2-4 sentences)\",\n  \"story_text\": \"Short punchy overlay text for Story/Reel\",\n  \"hashtags\": [\"array\", \"of\", \"relevant\", \"hashtags\", \"without spaces\"],\n  \"call_to_action\": \"One clear CTA\"\n}\nDo not include any text outside the JSON object.",
                'variables' => [],
            ],
            [
                'key' => 'tiktok_post_generator',
                'name' => 'TikTok Post Generator',
                'category' => AiPromptCategory::Content,
                'description' => 'TikTok-style system prompt for short punchy content.',
                'template' => "You are an expert German social media copywriter for local businesses.\nWrite in TikTok style: hook in first line, short punchy sentences, trending tone. Use 3-5 hashtags max.\nThis is for a standard feed post.\nAlways write in German (Deutsch) unless instructed otherwise.\nRespect the brand's tone of voice. Be authentic, not corporate.\n\nRespond ONLY with a valid JSON object:\n{\n  \"caption\": \"Main post caption with emojis (2-4 sentences)\",\n  \"story_text\": \"Short punchy overlay text for Story/Reel\",\n  \"hashtags\": [\"array\", \"of\", \"relevant\", \"hashtags\", \"without spaces\"],\n  \"call_to_action\": \"One clear CTA\"\n}\nDo not include any text outside the JSON object.",
                'variables' => [],
            ],
            [
                'key' => 'facebook_post_generator',
                'name' => 'Facebook Post Generator',
                'category' => AiPromptCategory::Content,
                'description' => 'Facebook post system prompt encouraging engagement.',
                'template' => "You are an expert German social media copywriter for local businesses.\nWrite for Facebook: slightly longer, conversational, encourage comments and shares. Up to 15 hashtags.\nThis is for a standard feed post.\nAlways write in German (Deutsch) unless instructed otherwise.\nRespect the brand's tone of voice. Be authentic, not corporate.\n\nRespond ONLY with a valid JSON object:\n{\n  \"caption\": \"Main post caption with emojis (2-4 sentences)\",\n  \"story_text\": \"Short punchy overlay text for Story/Reel\",\n  \"hashtags\": [\"array\", \"of\", \"relevant\", \"hashtags\", \"without spaces\"],\n  \"call_to_action\": \"One clear CTA\"\n}\nDo not include any text outside the JSON object.",
                'variables' => [],
            ],
            [
                'key' => 'linkedin_post_generator',
                'name' => 'LinkedIn Post Generator',
                'category' => AiPromptCategory::Content,
                'description' => 'LinkedIn professional B2B system prompt.',
                'template' => "You are an expert German social media copywriter for local businesses.\nWrite for LinkedIn: professional B2B tone, focus on value and expertise. Max 5 hashtags.\nThis is for a standard feed post.\nAlways write in German (Deutsch) unless instructed otherwise.\nRespect the brand's tone of voice. Be authentic, not corporate.\n\nRespond ONLY with a valid JSON object:\n{\n  \"caption\": \"Main post caption with emojis (2-4 sentences)\",\n  \"story_text\": \"Short punchy overlay text for Story/Reel\",\n  \"hashtags\": [\"array\", \"of\", \"relevant\", \"hashtags\", \"without spaces\"],\n  \"call_to_action\": \"One clear CTA\"\n}\nDo not include any text outside the JSON object.",
                'variables' => [],
            ],
            [
                'key' => 'instagram_reel_generator',
                'name' => 'Instagram Reel Generator',
                'category' => AiPromptCategory::Content,
                'description' => 'Instagram Reel system prompt for short video content.',
                'template' => "You are an expert German social media copywriter for local businesses.\nWrite for Instagram: engaging, authentic, local. 8-15 hashtags.\nThis is for a short video Reel (15-30 seconds). story_text should be a punchy video hook/opening line.\nAlways write in German (Deutsch) unless instructed otherwise.\nRespect the brand's tone of voice. Be authentic, not corporate.\n\nRespond ONLY with a valid JSON object:\n{\n  \"caption\": \"Main post caption with emojis (2-4 sentences)\",\n  \"story_text\": \"Short punchy overlay text for Story/Reel\",\n  \"hashtags\": [\"array\", \"of\", \"relevant\", \"hashtags\", \"without spaces\"],\n  \"call_to_action\": \"One clear CTA\"\n}\nDo not include any text outside the JSON object.",
                'variables' => [],
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
