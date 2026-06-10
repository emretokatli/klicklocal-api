# Klicklocal — AI Faz 1 & Faz 2 Claude Code Promptları

---

## FAZ 1 — PROMPT A: GPT Image 1 Görsel Üretimi (Backend)

```
You are working on the Klicklocal Laravel 12 backend at D:\NEWxampp\htdocs\klicklocal\backend.

MEVCUT YAPI (değiştirme, üzerine ekle):
- OpenAiClientInterface: backend/app/Services/Ai/Contracts/OpenAiClientInterface.php
  → şu an sadece generateContent() metodu var
- OpenAiClient: backend/app/Services/Ai/OpenAiClient.php
  → Http::withToken($this->apiKey) ile OpenAI API çağırıyor
- FakeOpenAiClient: backend/app/Services/Ai/FakeOpenAiClient.php
  → OPENAI_DRIVER=fake olunca devreye girer
- AppServiceProvider: backend/app/Providers/AppServiceProvider.php
  → OpenAiClientInterface binding burada
- AiGeneration model: backend/app/Models/AiGeneration.php
  → caption, story_text, hashtags, call_to_action, model, tokens_used alanları var
- AiContentGenerationService: backend/app/Services/Ai/AiContentGenerationService.php
  → generate() metodu business profile'dan içerik üretiyor
- Media model: backend/app/Models/Media.php
- MediaService: backend/app/Services/Media/MediaService.php (inceleyerek url() metodunu bul)

---

GÖREV 1 — GeneratedImageDTO oluştur

Dosya: backend/app/Services/Ai/DTOs/GeneratedImageDTO.php

```php
namespace App\Services\Ai\DTOs;

class GeneratedImageDTO
{
    public function __construct(
        public readonly string $imageUrl,     // URL or base64 data URL
        public readonly string $model,
        public readonly string $revisedPrompt = '',
        public readonly bool $isFake = false,
    ) {}
}
```

---

GÖREV 2 — OpenAiClientInterface'e generateImage() ekle

backend/app/Services/Ai/Contracts/OpenAiClientInterface.php dosyasını aç.
Mevcut generateContent() metodunu KORU, sadece aşağıdaki metodu ekle:

```php
/**
 * Generate an image using gpt-image-1 (or fake placeholder).
 * Returns a GeneratedImageDTO with a public URL.
 *
 * @param  array<string, string>  $context  Business profile context for fake fallback.
 */
public function generateImage(
    string $prompt,
    array $context = [],
    string $size = '1024x1024',
    string $quality = 'standard',
): GeneratedImageDTO;
```

---

GÖREV 3 — OpenAiClient'e gerçek implementasyon ekle

backend/app/Services/Ai/OpenAiClient.php dosyasını aç.
Mevcut generateContent() metodunu KORU. Aşağıdaki metodu sınıfa ekle:

```php
public function generateImage(
    string $prompt,
    array $context = [],
    string $size = '1024x1024',
    string $quality = 'standard',
): GeneratedImageDTO {
    if ($this->apiKey === '') {
        throw ValidationException::withMessages([
            'ai' => ['AI is not configured. Set OPENAI_API_KEY on the server.'],
        ]);
    }

    $response = Http::withToken($this->apiKey)
        ->timeout($this->timeout)
        ->acceptJson()
        ->post(rtrim($this->baseUrl, '/').'/images/generations', [
            'model'   => 'gpt-image-1',
            'prompt'  => $prompt,
            'n'       => 1,
            'size'    => $size,
            'quality' => $quality,
        ]);

    if ($response->failed()) {
        throw ValidationException::withMessages([
            'ai' => ['Image generation failed. Please try again.'],
        ]);
    }

    $payload = $response->json();
    $imageUrl = data_get($payload, 'data.0.url', '');
    $revisedPrompt = data_get($payload, 'data.0.revised_prompt', '');

    if (empty($imageUrl)) {
        throw ValidationException::withMessages([
            'ai' => ['No image returned from AI provider.'],
        ]);
    }

    return new GeneratedImageDTO(
        imageUrl: $imageUrl,
        model: 'gpt-image-1',
        revisedPrompt: $revisedPrompt,
    );
}
```

Import ekle: `use App\Services\Ai\DTOs\GeneratedImageDTO;`

---

GÖREV 4 — FakeOpenAiClient'e fake implementasyon ekle

backend/app/Services/Ai/FakeOpenAiClient.php dosyasını aç.
Aşağıdaki metodu ekle:

```php
public function generateImage(
    string $prompt,
    array $context = [],
    string $size = '1024x1024',
    string $quality = 'standard',
): GeneratedImageDTO {
    $name = urlencode($context['business_name'] ?? 'Business');
    $type = urlencode($context['business_type'] ?? 'Local');

    // picsum gives a deterministic placeholder image
    $seed = abs(crc32($name.$type.$prompt)) % 1000;
    $imageUrl = "https://picsum.photos/seed/{$seed}/1024/1024";

    return new GeneratedImageDTO(
        imageUrl: $imageUrl,
        model: 'fake-image-model',
        revisedPrompt: $prompt,
        isFake: true,
    );
}
```

Import ekle: `use App\Services\Ai\DTOs\GeneratedImageDTO;`

---

GÖREV 5 — ImageGenerationService oluştur

Dosya: backend/app/Services/Ai/ImageGenerationService.php

Bu servis:
1. BusinessProfile ve user prompt'tan bir görsel prompt oluşturur
2. OpenAiClientInterface::generateImage() çağırır
3. Dönen URL'yi bir AiGeneration kaydı ile ilişkilendirir (generated_image_url alanı üzerinden)
4. İsteğe bağlı olarak görseli Media tablosuna da kaydeder

```php
namespace App\Services\Ai;

use App\Models\AiGeneration;
use App\Models\BusinessProfile;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\Contracts\OpenAiClientInterface;
use App\Services\Ai\DTOs\GeneratedImageDTO;
use App\Services\Authorization\AuthorizationService;
use App\Services\Workspace\WorkspaceService;
use App\Support\Permission;
use Illuminate\Validation\ValidationException;

class ImageGenerationService
{
    public function __construct(
        private readonly OpenAiClientInterface $client,
        private readonly WorkspaceService $workspaceService,
        private readonly AuthorizationService $authorization,
    ) {}

    public function generate(
        User $user,
        int $workspaceId,
        string $userPrompt = '',
        string $platform = 'instagram',
        string $contentType = 'post',
        string $size = '1024x1024',
    ): GeneratedImageDTO {
        $workspace = $this->workspaceService->findForUser($user, $workspaceId);

        if (! $this->authorization->hasWorkspacePermission($user, $workspace, Permission::CREATE_POSTS)) {
            throw ValidationException::withMessages([
                'workspace' => ['You do not have permission to generate images in this workspace.'],
            ]);
        }

        $profile = $workspace->businessProfile;

        if ($profile === null || ! $profile->isComplete()) {
            throw ValidationException::withMessages([
                'business_profile' => ['Complete your business profile before generating images.'],
            ]);
        }

        $prompt = $this->buildPrompt($profile, $userPrompt, $platform, $contentType);
        $context = $this->buildContext($profile);

        return $this->client->generateImage($prompt, $context, $size);
    }

    private function buildPrompt(
        BusinessProfile $profile,
        string $userPrompt,
        string $platform,
        string $contentType,
    ): string {
        $parts = [
            "Professional social media {$contentType} image for a {$profile->business_type} called \"{$profile->business_name}\"",
        ];

        if ($profile->city) {
            $parts[] = "located in {$profile->city}, Germany";
        }

        if ($profile->description) {
            $parts[] = "Business description: {$profile->description}";
        }

        if ($profile->tone_of_voice) {
            $parts[] = "Visual style should match tone: {$profile->tone_of_voice}";
        }

        // Platform-specific aspect ratio hint
        $aspectHint = match ($platform) {
            'tiktok', 'instagram_reel' => 'vertical 9:16 format',
            'instagram_story'          => 'vertical 9:16 format',
            default                    => 'square 1:1 format suitable for Instagram feed',
        };
        $parts[] = "Format: {$aspectHint}";
        $parts[] = 'High quality, photorealistic, suitable for local business marketing.';
        $parts[] = 'No text overlays.';

        if ($userPrompt !== '') {
            $parts[] = "Specific request: {$userPrompt}";
        }

        return implode('. ', $parts);
    }

    /** @return array<string, string> */
    private function buildContext(BusinessProfile $profile): array
    {
        return [
            'business_name' => (string) $profile->business_name,
            'business_type' => (string) $profile->business_type,
            'city'          => (string) $profile->city,
        ];
    }
}
```

---

GÖREV 6 — AiGeneration modeline generated_image_url ekle

Migration oluştur:
```
php artisan make:migration add_generated_image_url_to_ai_generations_table
```

Migration içeriği:
```php
Schema::table('ai_generations', function (Blueprint $table) {
    $table->string('generated_image_url')->nullable()->after('raw_response');
    $table->string('image_model')->nullable()->after('generated_image_url');
    $table->string('image_revised_prompt')->nullable()->after('image_model');
});
```

AiGeneration modeline ekle:
- $fillable listesine: 'generated_image_url', 'image_model', 'image_revised_prompt'

---

GÖREV 7 — Controller: generate-image endpoint

backend/app/Http/Controllers/Api/V1/AiContentController.php dosyasını aç.
Mevcut generate() ve index() metodlarını KORU. Aşağıdaki metodu ekle:

```php
public function generateImage(Request $request): JsonResponse
{
    $validated = $request->validate([
        'workspace_id'  => ['required', 'integer', 'exists:workspaces,id'],
        'prompt'        => ['nullable', 'string', 'max:500'],
        'platform'      => ['nullable', 'string', 'in:instagram,facebook,tiktok,linkedin'],
        'content_type'  => ['nullable', 'string', 'in:post,reel,story,video'],
        'generation_id' => ['nullable', 'integer', 'exists:ai_generations,id'],
    ]);

    $imageService = app(\App\Services\Ai\ImageGenerationService::class);

    $dto = $imageService->generate(
        user: $request->user(),
        workspaceId: (int) $validated['workspace_id'],
        userPrompt: $validated['prompt'] ?? '',
        platform: $validated['platform'] ?? 'instagram',
        contentType: $validated['content_type'] ?? 'post',
    );

    // Optionally attach image URL to an existing AiGeneration record
    if (isset($validated['generation_id'])) {
        \App\Models\AiGeneration::query()
            ->where('id', $validated['generation_id'])
            ->where('workspace_id', $validated['workspace_id'])
            ->update([
                'generated_image_url'   => $dto->imageUrl,
                'image_model'           => $dto->model,
                'image_revised_prompt'  => $dto->revisedPrompt,
            ]);
    }

    return ApiResponse::success([
        'image_url'      => $dto->imageUrl,
        'model'          => $dto->model,
        'revised_prompt' => $dto->revisedPrompt,
    ], 'Image generated.', 201);
}
```

---

GÖREV 8 — Route ekle

backend/routes/api.php dosyasında workspace.team middleware grubunun içine ekle:
```php
Route::post('ai/generate-image', [AiContentController::class, 'generateImage'])
     ->middleware('feature.quota:ai_generation');
```

---

GÖREV 9 — Migrate ve test et

```bash
cd backend
php artisan migrate
php artisan test --filter=Ai
```

Eğer hata varsa düzelt. Build hatasız geçmeli.
```

---

## FAZ 1 — PROMPT B: Gelişmiş Prompt Sistemi (Platform + SEO + Tone)

```
You are working on the Klicklocal Laravel 12 backend at D:\NEWxampp\htdocs\klicklocal\backend.

MEVCUT YAPI:
- AiContentGenerationService: backend/app/Services/Ai/AiContentGenerationService.php
  → generate() metodu var, systemPrompt() ve userPrompt() private metodları var
  → systemPrompt() AiPromptTemplate'den 'instagram_post_generator' key'ini çekiyor
- AiPromptService: backend/app/Services/Ai/AiPromptService.php
  → activeTemplate(key) metodu var
- GenerateContentRequest: backend/app/Http/Requests/Ai/GenerateContentRequest.php
  → inceleyerek mevcut field'ları gör
- BusinessProfile model: mevcut alanlar: business_name, business_type, city, description,
  tone_of_voice, products_services, website, target_audience, unique_value_proposition,
  additional_notes, primary_goal

---

GÖREV 1 — GenerateContentRequest'e yeni alanlar ekle

backend/app/Http/Requests/Ai/GenerateContentRequest.php dosyasını aç ve rules() metoduna ekle:
```php
'platform'     => ['nullable', 'string', 'in:instagram,facebook,tiktok,linkedin'],
'content_type' => ['nullable', 'string', 'in:post,reel,story,video'],
'language'     => ['nullable', 'string', 'in:de,en', 'default:de'],
'seo_focus'    => ['nullable', 'string', 'max:100'],  // e.g. "Friseur München", "Pizzeria Berlin"
```

---

GÖREV 2 — AiContentGenerationService'i genişlet

backend/app/Services/Ai/AiContentGenerationService.php dosyasını aç.

generate() metodundaki $data array'ini genişlet:
```php
$platform    = $data['platform']    ?? 'instagram';
$contentType = $data['content_type'] ?? 'post';
$seoFocus    = $data['seo_focus']    ?? null;
```

systemPrompt() metodunu şu şekilde güncelle — platform bazlı farklı promptlar kullan:

```php
private function systemPrompt(BusinessProfile $profile, string $platform = 'instagram', string $contentType = 'post'): string
{
    // Try to load a platform-specific template first, then fall back to generic
    $templateKey = "{$platform}_{$contentType}_generator";
    $template = $this->prompts->activeTemplate($templateKey)
        ?? $this->prompts->activeTemplate('instagram_post_generator');

    if ($template !== null) {
        return $template->template;
    }

    $platformInstructions = match ($platform) {
        'tiktok'    => "Write in TikTok style: hook in first line, short punchy sentences, trending tone. Use 3-5 hashtags max.",
        'facebook'  => "Write for Facebook: slightly longer, conversational, encourage comments and shares. Up to 15 hashtags.",
        'linkedin'  => "Write for LinkedIn: professional B2B tone, focus on value and expertise. Max 5 hashtags.",
        default     => "Write for Instagram: engaging, authentic, local. 8-15 hashtags.",
    };

    $contentInstructions = match ($contentType) {
        'reel'  => "This is for a short video Reel (15-30 seconds). story_text should be a punchy video hook/opening line.",
        'story' => "This is for a Story. story_text should be a short overlay text for a vertical image.",
        'video' => "This is for a video post. caption should describe what viewers will see.",
        default => "This is for a standard feed post.",
    };

    return <<<PROMPT
    You are an expert German social media copywriter for local businesses.
    {$platformInstructions}
    {$contentInstructions}
    Always write in German (Deutsch) unless instructed otherwise.
    Respect the brand's tone of voice. Be authentic, not corporate.

    Respond ONLY with a valid JSON object:
    {
      "caption": "Main post caption with emojis (2-4 sentences)",
      "story_text": "Short punchy overlay text for Story/Reel",
      "hashtags": ["array", "of", "relevant", "hashtags", "without spaces"],
      "call_to_action": "One clear CTA"
    }
    Do not include any text outside the JSON object.
    PROMPT;
}
```

userPrompt() metodunu şu şekilde güncelle — SEO + extended profile fields:

```php
private function userPrompt(BusinessProfile $profile, string $userPrompt, ?string $seoFocus = null): string
{
    $lines = [
        'Business name: '    . $profile->business_name,
        'Business type: '    . ($profile->business_type ?: 'n/a'),
        'City: '             . ($profile->city ?: 'n/a'),
        'Tone of voice: '    . ($profile->tone_of_voice ?: 'freundlich und einladend'),
        'Description: '      . ($profile->description ?: 'n/a'),
        'Products / services: ' . ($profile->products_services ?: 'n/a'),
    ];

    // Include extended onboarding fields if available
    if ($profile->target_audience) {
        $lines[] = 'Target audience: ' . $profile->target_audience;
    }
    if ($profile->unique_value_proposition) {
        $lines[] = 'Unique value: ' . $profile->unique_value_proposition;
    }
    if ($profile->primary_goal) {
        $lines[] = 'Primary goal: ' . $profile->primary_goal;
    }

    // SEO injection
    if ($seoFocus) {
        $lines[] = "SEO focus keywords to naturally include in caption: {$seoFocus}";
        $lines[] = "Also add location-based hashtags related to: {$seoFocus}";
    } elseif ($profile->city && $profile->business_type) {
        $lines[] = "Naturally mention the city ({$profile->city}) in caption to help local SEO.";
        $lines[] = "Include at least 2 location hashtags like #{$profile->city} or #{$profile->city}" . ucfirst(strtolower($profile->business_type ?? '')) . ".";
    }

    if ($userPrompt !== '') {
        $lines[] = 'Specific request for this post: ' . $userPrompt;
    }

    $lines[] = 'Generate one social media post in German.';

    return implode("\n", $lines);
}
```

generate() metodunda systemPrompt() ve userPrompt() çağrılarını güncelle:
```php
$generated = $this->client->generateContent(
    $this->systemPrompt($profile, $platform, $contentType),
    $this->userPrompt($profile, $userPrompt, $seoFocus),
    $imageUrl,
    $this->context($profile),
);
```

AiGeneration::create() çağrısına ekle:
```php
'platform'     => $platform,
'content_type' => $contentType,
'seo_focus'    => $seoFocus,
```

---

GÖREV 3 — AiGeneration modeline yeni alanlar ekle

Migration:
```bash
php artisan make:migration add_platform_fields_to_ai_generations_table
```
```php
Schema::table('ai_generations', function (Blueprint $table) {
    $table->string('platform')->default('instagram')->after('prompt');
    $table->string('content_type')->default('post')->after('platform');
    $table->string('seo_focus')->nullable()->after('content_type');
});
```

AiGeneration $fillable'a ekle: 'platform', 'content_type', 'seo_focus'

---

GÖREV 4 — AiPromptTemplate seed ekle

backend/database/seeders/AiPromptSeeder.php oluştur (eğer yoksa).
Her platform için default template kaydet:

```php
$templates = [
    ['key' => 'instagram_post_generator', ...],
    ['key' => 'tiktok_post_generator', ...],
    ['key' => 'facebook_post_generator', ...],
    ['key' => 'linkedin_post_generator', ...],
    ['key' => 'instagram_reel_generator', ...],
];
```

Template içerikleri GÖREV 2'deki systemPrompt() metodundaki platform-specific string'lerden alınacak.
is_active = true, category = 'content_generation' olarak kaydet.

Seeder'ı DatabaseSeeder'a ekle.

---

GÖREV 5 — Migrate ve test et

```bash
cd backend
php artisan migrate
php artisan db:seed --class=AiPromptSeeder
php artisan test
```
```

---

## FAZ 1 — PROMPT C: Frontend AI Studio Güncelleme

```
You are working on the Klicklocal Next.js 16 frontend at D:\NEWxampp\htdocs\klicklocal\frontend.

MEVCUT YAPI:
- AI Studio page: frontend/src/app/(dashboard)/ai/page.tsx
- ReelStudio component: frontend/src/components/ai/reel-studio/ReelStudio.tsx
- billingService: frontend/src/services/billing.service.ts
- api-client: frontend/src/services/api-client.ts (apiPost, apiGet helpers)
- i18n: frontend/src/lib/i18n/de.ts
- Backend yeni endpoint'leri:
    POST /api/v1/ai/generate → text+hashtag (platform, content_type, seo_focus alanları eklendi)
    POST /api/v1/ai/generate-image → görsel üretimi

---

GÖREV 1 — aiService oluştur

frontend/src/services/ai.service.ts dosyası oluştur:

```typescript
import { apiGet, apiPost } from './api-client';

export interface GenerateContentParams {
  workspace_id: number;
  prompt?: string;
  media_id?: number;
  platform?: 'instagram' | 'facebook' | 'tiktok' | 'linkedin';
  content_type?: 'post' | 'reel' | 'story' | 'video';
  seo_focus?: string;
}

export interface GenerateImageParams {
  workspace_id: number;
  prompt?: string;
  platform?: string;
  content_type?: string;
  generation_id?: number;
}

export interface AiGeneration {
  id: number;
  caption: string;
  story_text: string;
  hashtags: string[];
  call_to_action: string;
  platform: string;
  content_type: string;
  generated_image_url: string | null;
  created_at: string;
}

export const aiService = {
  generate: (params: GenerateContentParams) =>
    apiPost<{ generation: AiGeneration }>('/ai/generate', params),

  generateImage: (params: GenerateImageParams) =>
    apiPost<{ image_url: string; model: string; revised_prompt: string }>(
      '/ai/generate-image',
      params,
    ),

  history: (workspaceId: number) =>
    apiGet<{ generations: AiGeneration[] }>(`/ai/generations?workspace_id=${workspaceId}`),
};
```

---

GÖREV 2 — ContentGenerationWizard bileşeni oluştur

frontend/src/components/ai/ContentGenerationWizard.tsx

Bu bileşen 3 adımlı inline bir form:

Adım 1 — Platform seçimi:
- 4 büyük toggle kartı: Instagram, Facebook, TikTok, LinkedIn
- Her biri tıklanabilir Card (shadcn/ui Card), seçilince border-primary görünür
- Alt kısımda "Weiter →" butonu

Adım 2 — İçerik türü:
- Platform'a göre dinamik seçenekler:
  - Instagram/TikTok: Post, Reel, Story
  - Facebook: Post, Video
  - LinkedIn: Post
- Aynı toggle Card tasarımı
- "Zurück" + "Weiter →" butonları

Adım 3 — İçerik ayarları ve üretim:
- Textarea: "Zusätzliche Anweisungen (optional)" — max 300 karakter — maps to `prompt`
- Input: "SEO-Fokus (optional, z.B. 'Friseur München')" — maps to `seo_focus`
- "Inhalt generieren" ana butonu (loading state ile)
- "Zurück" butonu

Sonuç gösterimi (adım 4, form kaybolur sonuç gelir):
- Büyük Card içinde:
  - Caption alanı: label "Caption", textarea (read-only), kopyalama butonu (lucide-react Copy icon)
  - Story Text alanı: aynı tasarım
  - Hashtags: küçük badge'ler olarak sıralı, tıklayınca kopyalanır
  - Call to Action: label + text + kopyalama butonu
  - "Bild dazu generieren" butonu → aiService.generateImage() çağırır
  - Eğer generated_image_url varsa: img tag ile göster (rounded-xl, max-h-64)
  - "Neuen Inhalt erstellen" butonu → wizard'ı resetler

State yönetimi:
- useState ile: step (1-3), platform, contentType, prompt, seoFocus, isLoading, generation, imageLoading, imageUrl
- workspaceId: useWorkspace() hook'undan al

Error handling:
- API hatalarında kırmızı Alert bileşeni göster

---

GÖREV 3 — /ai sayfasını güncelle

frontend/src/app/(dashboard)/ai/page.tsx dosyasını aç (önce oku).

Sayfanın üstüne tab sistemi ekle:
- shadcn/ui Tabs bileşenini kullan
- Tab 1: "🎬 Reel Studio" → mevcut ReelStudio bileşeni
- Tab 2: "✍️ Post Generator" → yeni ContentGenerationWizard bileşeni
- Default açık tab: "post-generator"

---

GÖREV 4 — i18n güncelle

frontend/src/lib/i18n/de.ts dosyasına de.ai veya de.aiWizard altına ekle:
```typescript
aiWizard: {
  title: 'KI-Inhalt erstellen',
  step1: 'Für welche Plattform?',
  step2: 'Welche Art von Inhalt?',
  step3: 'Anweisungen & Generieren',
  promptLabel: 'Zusätzliche Anweisungen (optional)',
  seoLabel: 'SEO-Fokus (optional)',
  seoPlaceholder: 'z.B. Friseur München, Pizzeria Berlin',
  generate: 'Inhalt generieren',
  generateImage: 'Bild dazu generieren',
  reset: 'Neuen Inhalt erstellen',
  copyCaption: 'Caption kopieren',
  copied: 'Kopiert!',
  back: 'Zurück',
  next: 'Weiter',
  resultTitle: 'Generierter Inhalt',
},
```

---

GÖREV 5 — Build kontrolü

```bash
cd frontend
npm run build
```

TypeScript hataları varsa düzelt. Lint uyarıları varsa düzelt.
```

---

## FAZ 2 — PROMPT A: Kling 3.0 Video Üretimi (Backend)

```
You are working on the Klicklocal Laravel 12 backend at D:\NEWxampp\htdocs\klicklocal\backend.

CONTEXT:
Kling API documentation: https://docs.kling.ai/
Kling 3.0 text-to-video endpoint: POST https://api.kling.ai/v1/videos/text2video
Auth: Bearer token (KLING_API_KEY env var)
Response is async: returns a task_id, must poll GET /v1/videos/{task_id} until status = 'succeed'
Typical generation time: 30-90 seconds for a 5-second clip

MEVCUT YAPI:
- OpenAiClientInterface pattern'ini takip et (Interface + Real + Fake implementasyonu)
- AppServiceProvider'da binding yapılıyor
- AiGeneration modeli: backend/app/Models/AiGeneration.php
- Queue/Jobs: backend/app/Jobs/ (PublishPostJob örneği var)

---

GÖREV 1 — VideoGenerationDTO oluştur

backend/app/Services/Ai/DTOs/VideoGenerationDTO.php:
```php
namespace App\Services\Ai\DTOs;

class VideoGenerationDTO
{
    public function __construct(
        public readonly string $taskId,
        public readonly string $status,     // 'pending', 'processing', 'succeed', 'failed'
        public readonly ?string $videoUrl,  // null until succeed
        public readonly string $model = 'kling-v3',
        public readonly bool $isFake = false,
    ) {}
}
```

---

GÖREV 2 — KlingClientInterface oluştur

backend/app/Services/Ai/Contracts/KlingClientInterface.php:
```php
namespace App\Services\Ai\Contracts;

use App\Services\Ai\DTOs\VideoGenerationDTO;

interface KlingClientInterface
{
    /**
     * Submit a text-to-video generation task.
     * Returns a DTO with taskId and initial status 'pending'.
     */
    public function createVideoTask(
        string $prompt,
        string $negativePrompt = '',
        string $duration = '5',      // '5' or '10' seconds
        string $aspectRatio = '9:16', // '9:16' for Reels, '1:1' for posts
        array $context = [],
    ): VideoGenerationDTO;

    /**
     * Poll task status. Returns updated DTO with videoUrl when complete.
     */
    public function getTaskStatus(string $taskId): VideoGenerationDTO;
}
```

---

GÖREV 3 — KlingClient (gerçek implementasyon) oluştur

backend/app/Services/Ai/KlingClient.php:

```php
namespace App\Services\Ai;

use App\Services\Ai\Contracts\KlingClientInterface;
use App\Services\Ai\DTOs\VideoGenerationDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class KlingClient implements KlingClientInterface
{
    private const BASE_URL = 'https://api.kling.ai/v1';

    public function __construct(
        private readonly string $apiKey,
        private readonly int $timeout = 30,
    ) {}

    public function createVideoTask(
        string $prompt,
        string $negativePrompt = '',
        string $duration = '5',
        string $aspectRatio = '9:16',
        array $context = [],
    ): VideoGenerationDTO {
        if ($this->apiKey === '') {
            throw ValidationException::withMessages([
                'ai' => ['Kling API key not configured. Set KLING_API_KEY.'],
            ]);
        }

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->post(self::BASE_URL . '/videos/text2video', [
                'model_name'      => 'kling-v1-5',
                'prompt'          => $prompt,
                'negative_prompt' => $negativePrompt,
                'cfg_scale'       => 0.5,
                'mode'            => 'std',
                'duration'        => $duration,
                'aspect_ratio'    => $aspectRatio,
            ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'ai' => ['Video generation request failed: ' . $response->body()],
            ]);
        }

        $taskId = data_get($response->json(), 'data.task_id', '');

        if (empty($taskId)) {
            throw ValidationException::withMessages([
                'ai' => ['No task ID returned from Kling API.'],
            ]);
        }

        return new VideoGenerationDTO(
            taskId: $taskId,
            status: 'pending',
            videoUrl: null,
            model: 'kling-v1-5',
        );
    }

    public function getTaskStatus(string $taskId): VideoGenerationDTO
    {
        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->get(self::BASE_URL . "/videos/text2video/{$taskId}");

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'ai' => ['Failed to fetch Kling task status.'],
            ]);
        }

        $data = $response->json();
        $status = data_get($data, 'data.task_status', 'pending');
        $videoUrl = data_get($data, 'data.task_result.videos.0.url');

        return new VideoGenerationDTO(
            taskId: $taskId,
            status: $status,
            videoUrl: $videoUrl,
            model: 'kling-v1-5',
        );
    }
}
```

---

GÖREV 4 — FakeKlingClient oluştur

backend/app/Services/Ai/FakeKlingClient.php:

```php
namespace App\Services\Ai;

use App\Services\Ai\Contracts\KlingClientInterface;
use App\Services\Ai\DTOs\VideoGenerationDTO;
use Illuminate\Support\Str;

class FakeKlingClient implements KlingClientInterface
{
    public function createVideoTask(
        string $prompt,
        string $negativePrompt = '',
        string $duration = '5',
        string $aspectRatio = '9:16',
        array $context = [],
    ): VideoGenerationDTO {
        return new VideoGenerationDTO(
            taskId: 'fake-task-' . Str::uuid(),
            status: 'pending',
            videoUrl: null,
            model: 'fake-kling',
            isFake: true,
        );
    }

    public function getTaskStatus(string $taskId): VideoGenerationDTO
    {
        // Fake: always return a placeholder video URL as "complete"
        return new VideoGenerationDTO(
            taskId: $taskId,
            status: 'succeed',
            videoUrl: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
            model: 'fake-kling',
            isFake: true,
        );
    }
}
```

---

GÖREV 5 — VideoGeneration modeli + migration oluştur

Migration: create_video_generations_table
```php
Schema::create('video_generations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('task_id')->unique();
    $table->string('status')->default('pending'); // pending, processing, succeed, failed
    $table->string('model')->default('kling-v1-5');
    $table->text('prompt');
    $table->string('platform')->default('instagram');
    $table->string('content_type')->default('reel');
    $table->string('aspect_ratio')->default('9:16');
    $table->string('duration')->default('5');
    $table->text('video_url')->nullable();
    $table->json('metadata')->nullable();
    $table->boolean('is_fake')->default(false);
    $table->timestamps();
});
```

Model: backend/app/Models/VideoGeneration.php
- fillable: tüm kolonlar
- casts: metadata array
- workspace() ve user() BelongsTo ilişkileri

---

GÖREV 6 — VideoGenerationService oluştur

backend/app/Services/Ai/VideoGenerationService.php:

```php
namespace App\Services\Ai;

use App\Models\VideoGeneration;
use App\Models\User;
use App\Services\Ai\Contracts\KlingClientInterface;
use App\Services\Authorization\AuthorizationService;
use App\Services\Workspace\WorkspaceService;
use App\Support\Permission;
use Illuminate\Validation\ValidationException;

class VideoGenerationService
{
    public function __construct(
        private readonly KlingClientInterface $client,
        private readonly WorkspaceService $workspaceService,
        private readonly AuthorizationService $authorization,
    ) {}

    public function create(
        User $user,
        int $workspaceId,
        string $userPrompt,
        string $platform = 'instagram',
        string $contentType = 'reel',
        string $duration = '5',
    ): VideoGeneration {
        $workspace = $this->workspaceService->findForUser($user, $workspaceId);

        if (! $this->authorization->hasWorkspacePermission($user, $workspace, Permission::CREATE_POSTS)) {
            throw ValidationException::withMessages([
                'workspace' => ['No permission to generate videos in this workspace.'],
            ]);
        }

        $profile = $workspace->businessProfile;
        if ($profile === null || ! $profile->isComplete()) {
            throw ValidationException::withMessages([
                'business_profile' => ['Complete your business profile first.'],
            ]);
        }

        $aspectRatio = in_array($platform, ['tiktok', 'instagram']) && in_array($contentType, ['reel', 'story'])
            ? '9:16'
            : '1:1';

        $prompt = $this->buildVideoPrompt($profile, $userPrompt, $platform, $contentType);
        $context = ['business_name' => $profile->business_name, 'business_type' => $profile->business_type ?? ''];

        $dto = $this->client->createVideoTask($prompt, '', $duration, $aspectRatio, $context);

        return VideoGeneration::create([
            'workspace_id' => $workspace->id,
            'user_id'      => $user->id,
            'task_id'      => $dto->taskId,
            'status'       => $dto->status,
            'model'        => $dto->model,
            'prompt'       => $prompt,
            'platform'     => $platform,
            'content_type' => $contentType,
            'aspect_ratio' => $aspectRatio,
            'duration'     => $duration,
            'video_url'    => $dto->videoUrl,
            'is_fake'      => $dto->isFake,
        ]);
    }

    public function pollStatus(VideoGeneration $generation): VideoGeneration
    {
        if (in_array($generation->status, ['succeed', 'failed'])) {
            return $generation;
        }

        $dto = $this->client->getTaskStatus($generation->task_id);
        $generation->update([
            'status'    => $dto->status,
            'video_url' => $dto->videoUrl,
        ]);

        return $generation->fresh();
    }

    private function buildVideoPrompt($profile, string $userPrompt, string $platform, string $contentType): string
    {
        $parts = [
            "Cinematic {$contentType} video for a local German {$profile->business_type} called \"{$profile->business_name}\"",
        ];
        if ($profile->city) { $parts[] = "in {$profile->city}"; }
        if ($profile->description) { $parts[] = $profile->description; }
        $parts[] = 'Warm, inviting, authentic atmosphere. No text overlays. Professional lighting.';
        if ($userPrompt !== '') { $parts[] = "Scene: {$userPrompt}"; }
        return implode('. ', $parts);
    }
}
```

---

GÖREV 7 — VideoGenerationController oluştur

backend/app/Http/Controllers/Api/V1/VideoGenerationController.php:

```php
public function store(Request $request): JsonResponse  // POST /ai/generate-video
public function status(Request $request, VideoGeneration $videoGeneration): JsonResponse  // GET /ai/video-status/{id}
public function index(Request $request): JsonResponse  // GET /ai/videos
```

store(): validates { workspace_id, prompt (required, max 500), platform, content_type, duration (in:5,10) }
Calls VideoGenerationService::create(), returns 202 (Accepted — async)

status(): returns current generation record (fresh from DB + optional poll)
If status = 'pending'|'processing': call pollStatus() to check Kling API
Returns updated record

index(): returns VideoGeneration::where('workspace_id', ...) latest 20

---

GÖREV 8 — Route ve AppServiceProvider bağlama

Routes (workspace.team middleware grubuna):
```php
Route::post('ai/generate-video', [VideoGenerationController::class, 'store']);
Route::get('ai/generate-video/{videoGeneration}', [VideoGenerationController::class, 'status']);
Route::get('ai/videos', [VideoGenerationController::class, 'index']);
```

AppServiceProvider'a ekle:
```php
$this->app->bind(KlingClientInterface::class, function ($app): KlingClientInterface {
    $config = $app['config']->get('services.kling', []);
    $driver = $config['driver'] ?? env('KLING_DRIVER', 'fake');

    if ($driver === 'fake') {
        return new FakeKlingClient;
    }

    return new KlingClient(
        apiKey: (string) ($config['key'] ?? ''),
        timeout: (int) ($config['timeout'] ?? 30),
    );
});
```

config/services.php'ye ekle:
```php
'kling' => [
    'driver'  => env('KLING_DRIVER', 'fake'),
    'key'     => env('KLING_API_KEY', ''),
    'timeout' => 30,
],
```

.env.example'a ekle:
```
KLING_DRIVER=fake
KLING_API_KEY=
```

---

GÖREV 9 — Migrate ve test

```bash
cd backend
php artisan migrate
php artisan test
```
```

---

## FAZ 2 — PROMPT B: Optimal Yayın Zamanı Önerisi

```
You are working on the Klicklocal monorepo at D:\NEWxampp\htdocs\klicklocal.

CONTEXT:
Amaç: Kullanıcıya "bu içeriği ne zaman paylaşmalısın?" önerisi sunmak.
Backend GPT-4o mevcut, extra API call gerekmez — deterministik veri yeterli.
Hem backend endpoint hem frontend UI component gerekiyor.

---

GÖREV 1 — Backend: Optimal time önerisi endpoint

backend/app/Http/Controllers/Api/V1/AiContentController.php dosyasına ekle:

```php
public function optimalPostingTime(Request $request): JsonResponse
{
    $request->validate([
        'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
        'platform'     => ['required', 'string', 'in:instagram,facebook,tiktok,linkedin'],
        'content_type' => ['nullable', 'string', 'in:post,reel,story,video'],
    ]);

    $workspace = $request->attributes->get('workspace');
    $platform  = $request->input('platform');
    $type      = $request->input('content_type', 'post');

    // Evidence-based optimal posting times per platform (German timezone CET/CEST)
    $schedule = [
        'instagram' => [
            'post'  => [['day' => 'Dienstag', 'times' => ['11:00', '14:00']], ['day' => 'Mittwoch', 'times' => ['11:00']], ['day' => 'Freitag', 'times' => ['10:00', '15:00']]],
            'reel'  => [['day' => 'Montag',   'times' => ['08:00', '19:00']], ['day' => 'Donnerstag', 'times' => ['19:00', '20:00']]],
            'story' => [['day' => 'täglich',  'times' => ['08:00', '12:00', '18:00']]],
        ],
        'tiktok' => [
            'post'  => [['day' => 'Dienstag', 'times' => ['07:00', '20:00']], ['day' => 'Freitag', 'times' => ['17:00', '20:00']], ['day' => 'Samstag', 'times' => ['11:00']]],
            'reel'  => [['day' => 'Dienstag', 'times' => ['19:00', '20:00']], ['day' => 'Donnerstag', 'times' => ['19:00']]],
            'video' => [['day' => 'Montag',   'times' => ['06:00', '10:00']]],
        ],
        'facebook' => [
            'post'  => [['day' => 'Mittwoch', 'times' => ['11:00', '13:00']], ['day' => 'Donnerstag', 'times' => ['13:00']], ['day' => 'Freitag', 'times' => ['13:00']]],
            'video' => [['day' => 'Mittwoch', 'times' => ['12:00']], ['day' => 'Samstag', 'times' => ['10:00']]],
        ],
        'linkedin' => [
            'post'  => [['day' => 'Dienstag', 'times' => ['08:00', '10:00']], ['day' => 'Mittwoch', 'times' => ['08:00', '12:00']], ['day' => 'Donnerstag', 'times' => ['09:00']]],
        ],
    ];

    $times = $schedule[$platform][$type] ?? $schedule[$platform]['post'] ?? [];

    return ApiResponse::success([
        'platform'     => $platform,
        'content_type' => $type,
        'timezone'     => 'Europe/Berlin',
        'suggestions'  => $times,
        'tip'          => $this->getPostingTip($platform, $type),
    ]);
}

private function getPostingTip(string $platform, string $type): string
{
    return match ($platform) {
        'tiktok'    => 'TikTok-Algorithmus bevorzugt Uploads kurz vor dem Feierabend (17-20 Uhr). Konsistenz ist wichtiger als perfektes Timing.',
        'instagram' => match ($type) {
            'reel'  => 'Reels erhalten 22% mehr Interaktionen als normale Posts. Abends zwischen 19-21 Uhr ist die aktivste Zeit.',
            'story' => 'Stories morgens (8-9 Uhr) und abends (18-19 Uhr) haben die höchsten Öffnungsraten.',
            default => 'Instagram-Feed-Posts performen dienstags und mittwochs am besten.',
        },
        'facebook'  => 'Facebook-Nutzer in Deutschland sind am stärksten mittwochs und donnerstags zwischen 11-14 Uhr aktiv.',
        'linkedin'  => 'LinkedIn-Content erzielt die höchste Reichweite dienstags bis donnerstags am Morgen (8-10 Uhr).',
        default     => 'Poste regelmäßig und analysiere deine eigenen Insights für individuelle Optimierungen.',
    };
}
```

Route ekle (workspace.team middleware grubuna):
```php
Route::get('ai/optimal-posting-time', [AiContentController::class, 'optimalPostingTime']);
```

---

GÖREV 2 — Frontend: PostingTimeSuggestion bileşeni

frontend/src/components/ai/PostingTimeSuggestion.tsx oluştur:

- Props: workspaceId, platform, contentType
- useQuery ile GET /api/v1/ai/optimal-posting-time?workspace_id=...&platform=...&content_type=... çağır
- Küçük bir Card: başlık "📅 Optimaler Zeitpunkt"
- Her suggestion için: "Dienstag um 11:00 & 14:00 Uhr" formatında göster
- Altta tip metni (küçük, muted renk)
- Timezone notu: "(Zeitzone: Europe/Berlin)"

Bu bileşeni ContentGenerationWizard'ın sonuç ekranına ekle (GÖREV 2 adım 3 sonrasında).
Platform ve contentType wizard state'inden alınır.

---

GÖREV 3 — Build kontrolü

```bash
cd frontend && npm run build
cd backend && php artisan test
```
```

---

## Kullanım Sırası (Tahmini Süre)

| # | Prompt | İçerik | Süre |
|---|--------|--------|------|
| Faz 1-A | GPT Image 1 Backend | Interface genişletme, ImageGenerationService, migration | 1-2 saat |
| Faz 1-B | Gelişmiş Promptlar | Platform/SEO bazlı prompt sistemi, seed | 1 saat |
| Faz 1-C | Frontend AI Studio | aiService, ContentGenerationWizard, tab sistemi | 2-3 saat |
| Faz 2-A | Kling Video Backend | KlingClient, VideoGeneration modeli, async polling | 2-3 saat |
| Faz 2-B | Posting Time | Deterministik öneri endpoint + UI bileşeni | 1 saat |

> **Toplam: ~1 gün** (Claude Code ile paralel çalışarak)
>
> Faz 1-A ve Faz 1-B backend'de paralel çalıştırılabilir.
> Faz 1-C, Faz 1-A + 1-B bitmeden başlatılmamalı.
> Faz 2-A backend ile Faz 1-C frontend paralel çalışabilir.
