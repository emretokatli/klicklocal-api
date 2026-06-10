# Klicklocal Web → Mobile Uyum Promptları

Mobil uygulama (Sichtbar/Appronix) ile aynı akışa getirmek için sırasıyla uygula.
Her prompt bağımsız olarak Claude Code'a verilebilir.

---

## PROMPT 1 — Navigation Sadeleştirme (12 → 7 öğe)

```
Klicklocal Next.js frontend'inde sidebar navigasyonunu mobil uygulama ile aynı hizaya getir.
Mobil uygulama 6 ana tab kullanıyor: Dashboard, Posts, AI, Social, Comments, Billing+Settings.
Web'deki gereksiz ve dağınık menü öğelerini sadeleştir.

### Yapılacak değişiklikler:

**1. `frontend/src/components/layout/Sidebar.tsx`**

`customerNav` array'ini aşağıdaki 7 öğeye düşür (sıra önemli):

```typescript
const customerNav: NavItem[] = [
  { href: '/dashboard',       label: de.nav.dashboard,      icon: LayoutDashboard },
  { href: '/ai',              label: de.nav.aiStudio,       icon: Sparkles },
  { href: '/posts',           label: de.nav.posts,          icon: SquarePen },
  { href: '/social-accounts', label: de.nav.socialAccounts, icon: Share2 },
  { href: '/comments',        label: de.nav.comments,       icon: MessageCircle },
  { href: '/billing',         label: de.nav.billing,        icon: CreditCard },
  { href: '/settings',        label: de.nav.settings,       icon: Settings },
];
```

- `MessageCircle` import'unu `lucide-react`'tan ekle
- Kaldırılan import'ları temizle: Calendar, FileText, FolderKanban, ImageIcon, Package, Plug, Receipt, Tag, BarChart3

**2. `frontend/src/lib/i18n/de.ts`**

`de.nav` bölümüne `comments` ekle:
```typescript
comments: 'Kommentare',
```
`media`, `calendar`, `invoices`, `transactions`, `workspaces`, `usage` alanları i18n'de kalabilir (diğer promptlarda kullanılacak), sadece sidebar'dan çıkar.

### Amaç:
Mobil uygulamadaki gibi net 7 öğeli navigasyon. Billing altında invoices+transactions tab olarak gösterilecek (Prompt 3). Settings altında workspaces+media+usage tab olacak (Prompt 4). Comments yeni bir sayfa (Prompt 5).
```

---

## PROMPT 2 — Register/Onboarding Akışı Sadeleştirme

```
Klicklocal Next.js frontend'inde 12 adımlı onboarding wizard'ını kaldır.
Mobil uygulamadaki gibi tek bir kayıt formu kullan: email + şifre + işletme adı + sektör.
Onboarding artık kullanıcıyı bloklamasın; profil bilgileri dashboard'dan tamamlanabilsin.

### Mevcut durum:
- `/register` → EmailRegisterForm (sadece email) → `/onboarding` (12 adım, bloklayıcı)
- OnboardingGate: `session.onboarding_completed` false ise `/onboarding`'e yönlendirir
- `POST /auth/register-email` → token alır
- `POST /auth/onboarding/complete` → workspace oluşturur, profil tamamlar

### Yapılacak değişiklikler:

**1. `frontend/src/app/register/page.tsx`**

`EmailRegisterForm` bileşenini kullanmak yerine yeni `SimpleRegisterForm` bileşenini kullan.
Bu bileşen aşağıdaki alanları içerir:
- Email (type="email")
- Şifre (type="password", min 8 karakter)
- Şifre tekrar (type="password")
- İşletme adı (text)
- Sektör (select dropdown, `ONBOARDING_INDUSTRIES` listesinden)

Form submit akışı:
1. `POST /auth/register-email` ile `{email}` gönder → token al, localStorage'a kaydet
2. Hemen `POST /auth/onboarding/complete` ile şu body'yi gönder:
   ```json
   {
     "password": "...",
     "password_confirmation": "...",
     "first_name": "",
     "business_name": "...",
     "industry": "..."
   }
   ```
3. `workspace.id`'yi `setStoredWorkspaceId(result.workspace.id)` ile sakla
4. `queryClient.resetQueries()` çağır
5. `router.replace('/dashboard')` ile yönlendir

Tasarım: mevcut `AuthShell` bileşenini kullan. Form single-page olsun, step yok.

**2. `frontend/src/components/auth/SimpleRegisterForm.tsx`** (YENİ DOSYA)

`ONBOARDING_INDUSTRIES` listesi `frontend/src/lib/onboarding-wizard/constants.ts`'den import edilecek.
`userOnboardingService` `frontend/src/services/user-onboarding.service.ts`'den import edilecek.

**3. `frontend/src/components/auth/OnboardingGate.tsx`**

`OnboardingGate` bileşenini değiştir:
- Artık `/onboarding`'e yönlendirme yapmasın
- `session.onboarding_completed` false ise children'ı yine de render et
- Sadece yükleme durumunu handle et

```typescript
export function OnboardingGate({ children }: { children: ReactNode }) {
  const { isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <LoadingSpinner />
      </div>
    );
  }

  return <>{children}</>;
}
```

`HomeOnboardingRedirect` da aynı şekilde yönlendirmeyi kaldır, sadece children döndürsün.
`CompleteOnboardingGuard` kalabilir ama artık `/onboarding` route'u olmayacak.

**4. `frontend/src/app/onboarding/page.tsx`**

Bu dosyayı sil veya içeriğini `/dashboard`'a redirect yap:
```typescript
import { redirect } from 'next/navigation';
export default function OnboardingPage() {
  redirect('/dashboard');
}
```

**5. `frontend/src/app/(dashboard)/dashboard/page.tsx`**

Dashboard sayfasına, kullanıcının `session.onboarding_completed` false ise (eski kullanıcılar) veya business profile eksikse bir bilgi banner'ı ekle:

```typescript
{session && !session.onboarding_completed && (
  <div className="mb-6 rounded-xl border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-on-surface">
    <strong>Profil tamamlanmadı.</strong> Daha iyi AI içerik üretimi için{' '}
    <Link href="/settings" className="underline">Einstellungen</Link> sayfasından
    işletme profilini tamamla.
  </div>
)}
```

### Amaç:
Mobil uygulamadaki gibi tek form → direkt dashboard. Kullanıcı kayıt sonrası bloklanmaz.
```

---

## PROMPT 3 — Billing Sayfası Konsolidasyonu (Invoices + Transactions Tab'a Taşı)

```
Klicklocal Next.js frontend'inde `/billing`, `/invoices` ve `/transactions` sayfalarını
tek bir `/billing` sayfasında 3 tab olarak birleştir. Mobil uygulamada billing tek bir bölüm.

### Mevcut durum:
- `frontend/src/app/(dashboard)/billing/page.tsx` — abonelik + top-up
- `frontend/src/app/(dashboard)/invoices/page.tsx` — fatura listesi (`billingService.invoices()`)
- `frontend/src/app/(dashboard)/transactions/page.tsx` — işlem geçmişi (`billingService.transactions()`)

### Yapılacak değişiklikler:

**1. `frontend/src/app/(dashboard)/billing/page.tsx`**

Sayfayı 3 tab'lı yapıya dönüştür. Tab component için mevcut UI bileşeni yoksa basit
`<button>` tabanlı state-driven tab sistemi kur (shadcn Tabs yoksa manuel).

Tab yapısı:
```
[Abonnement] [Rechnungen] [Transaktionen]
```

- **Abonnement tab** (varsayılan): Mevcut billing sayfasının tüm içeriği (plan, usage meters, top-up)
- **Rechnungen tab**: `frontend/src/app/(dashboard)/invoices/page.tsx`'in içeriği (tablo, `billingService.invoices()`)
- **Transaktionen tab**: `frontend/src/app/(dashboard)/transactions/page.tsx`'in içeriği (tablo, `billingService.transactions()`)

PageHeader sadece bir kez, en üstte gösterilsin. Her tab `workspaceId` kontrolü yapsın.

Mevcut `billingService` import'larını aynen koru. `formatMoney` ve `de.billing.*` key'leri kullanılmaya devam edilsin.

**2. `frontend/src/app/(dashboard)/invoices/page.tsx`**

İçeriği temizle, sadece redirect yaz:
```typescript
import { redirect } from 'next/navigation';
export default function InvoicesPage() {
  redirect('/billing');
}
```

**3. `frontend/src/app/(dashboard)/transactions/page.tsx`**

İçeriği temizle, sadece redirect yaz:
```typescript
import { redirect } from 'next/navigation';
export default function TransactionsPage() {
  redirect('/billing');
}
```

### Amaç:
Kullanıcı tek `/billing` sayfasında tüm finansal bilgiye erişir. Sidebar öğe sayısı azalır.
```

---

## PROMPT 4 — Settings Sayfası Genişletme (Workspaces + Business Profile + Media + Usage Tab'a Taşı)

```
Klicklocal Next.js frontend'inde `/settings`, `/workspaces`, `/media`, `/usage` sayfalarını
tek bir `/settings` sayfasında 4 tab olarak birleştir. Mobil uygulamada bunlar tek bir ayarlar bölümü.

### Mevcut durum:
- `frontend/src/app/(dashboard)/settings/page.tsx` — kullanıcı profili (ad, email, roller)
- `frontend/src/app/(dashboard)/workspaces/page.tsx` — workspace listesi ve seçim
- `frontend/src/app/(dashboard)/media/page.tsx` — medya kütüphanesi
- `frontend/src/app/(dashboard)/usage/page.tsx` — kullanım istatistikleri

### Yapılacak değişiklikler:

**1. `frontend/src/app/(dashboard)/settings/page.tsx`**

Sayfayı 4 tab'lı yapıya dönüştür:

```
[Profil] [Workspace] [Medien] [Nutzung]
```

- **Profil tab** (varsayılan): Mevcut settings sayfasının tüm içeriği (ad, email, roller, subscription limits).
  Ayrıca business profile düzenleme formunu ekle: `BusinessProfileForm` bileşenini
  `frontend/src/components/business/BusinessProfileForm.tsx`'den import et.

- **Workspace tab**: `frontend/src/app/(dashboard)/workspaces/page.tsx`'in içeriği.
  Workspace listesi, aktif workspace seçimi, yeni workspace oluşturma.

- **Medien tab**: `frontend/src/app/(dashboard)/media/page.tsx`'in içeriği.
  Medya upload, liste, önizleme.

- **Nutzung tab**: `frontend/src/app/(dashboard)/usage/page.tsx`'in içeriği.
  `UsageMeters` bileşeni veya kullanım istatistikleri.

**2. `frontend/src/app/(dashboard)/workspaces/page.tsx`**
İçeriği temizle, redirect yaz:
```typescript
import { redirect } from 'next/navigation';
export default function WorkspacesPage() { redirect('/settings'); }
```

**3. `frontend/src/app/(dashboard)/media/page.tsx`**
İçeriği temizle, redirect yaz:
```typescript
import { redirect } from 'next/navigation';
export default function MediaPage() { redirect('/settings'); }
```

**4. `frontend/src/app/(dashboard)/usage/page.tsx`**
İçeriği temizle, redirect yaz:
```typescript
import { redirect } from 'next/navigation';
export default function UsagePage() { redirect('/settings'); }
```

**5. `frontend/src/app/(dashboard)/calendar/page.tsx`**
Takvim özelliği için şimdilik Posts sayfasına yönlendir:
```typescript
import { redirect } from 'next/navigation';
export default function CalendarPage() { redirect('/posts'); }
```

### Amaç:
Mobil uygulamadaki gibi tek ayarlar bölümü. Sidebar temizlenir, kullanıcı kaybolmaz.
```

---

## PROMPT 5 — Yeni Comments (Yorumlar) Sayfası — Backend + Frontend

```
Klicklocal uygulamasına mobil uygulamadaki gibi bir "Kommentare" (Yorumlar) sayfası ekle.
Mobil uygulama: yorumlar platform (instagram/tiktok/facebook), yazar, metin, sentiment (positive/neutral/negative), tarih gösterir.

Bu özellik sıfırdan ekleniyor, backend'de endpoint yok. Her iki tarafı da implemente et.

### BACKEND KISMI

**1. Migration: `database/migrations/2026_06_09_000001_create_comments_table.php`**

```php
Schema::create('comments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
    $table->enum('platform', ['instagram', 'tiktok', 'facebook', 'linkedin']);
    $table->string('external_id')->nullable(); // Platform'dan gelen yorum ID'si
    $table->string('author');                   // Yorum yapan kullanıcı adı
    $table->text('text');
    $table->enum('sentiment', ['positive', 'neutral', 'negative'])->default('neutral');
    $table->timestamp('commented_at')->nullable();
    $table->timestamps();
    $table->index(['workspace_id', 'platform']);
});
```

**2. Model: `app/Models/Comment.php`**

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    protected $fillable = [
        'workspace_id', 'post_id', 'platform', 'external_id',
        'author', 'text', 'sentiment', 'commented_at',
    ];

    protected $casts = ['commented_at' => 'datetime'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
```

**3. Controller: `app/Http/Controllers/Api/V1/CommentController.php`**

İki endpoint:
- `GET /comments` — workspace'in yorumlarını listele (filtre: platform, sentiment)
- `POST /comments` — manuel yorum ekle (test/demo için)

```php
<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'platform'     => ['nullable', 'string', 'in:instagram,tiktok,facebook,linkedin'],
            'sentiment'    => ['nullable', 'string', 'in:positive,neutral,negative'],
            'limit'        => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $workspace = $request->attributes->get('workspace');

        $query = Comment::query()
            ->where('workspace_id', $workspace->id)
            ->latest('commented_at');

        if (!empty($validated['platform'])) {
            $query->where('platform', $validated['platform']);
        }
        if (!empty($validated['sentiment'])) {
            $query->where('sentiment', $validated['sentiment']);
        }

        $comments = $query->limit($validated['limit'] ?? 50)->get();

        return ApiResponse::success(['comments' => $comments]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'platform'     => ['required', 'string', 'in:instagram,tiktok,facebook,linkedin'],
            'author'       => ['required', 'string', 'max:255'],
            'text'         => ['required', 'string', 'max:2000'],
            'sentiment'    => ['nullable', 'string', 'in:positive,neutral,negative'],
            'commented_at' => ['nullable', 'date'],
        ]);

        $workspace = $request->attributes->get('workspace');

        $comment = Comment::create([
            'workspace_id' => $workspace->id,
            'platform'     => $validated['platform'],
            'author'       => $validated['author'],
            'text'         => $validated['text'],
            'sentiment'    => $validated['sentiment'] ?? 'neutral',
            'commented_at' => $validated['commented_at'] ?? now(),
        ]);

        return ApiResponse::success(['comment' => $comment], 'Comment created.', 201);
    }
}
```

**4. Routes: `routes/api.php`**

Mevcut workspace.team middleware grubu içinde, subscription.required OLMADAN ekle:

```php
// Comments (workspace scoped, no subscription required)
Route::get('/comments', [CommentController::class, 'index']);
Route::post('/comments', [CommentController::class, 'store']);
```

Import ekle: `use App\Http\Controllers\Api\V1\CommentController;`

**5. Migration çalıştır:**
```
php artisan migrate
```

---

### FRONTEND KISMI

**6. Types: `frontend/src/types/api.ts`**

`Comment` tipini ekle:
```typescript
export interface Comment {
  id: number;
  workspace_id: number;
  post_id: number | null;
  platform: 'instagram' | 'tiktok' | 'facebook' | 'linkedin';
  external_id: string | null;
  author: string;
  text: string;
  sentiment: 'positive' | 'neutral' | 'negative';
  commented_at: string | null;
  created_at: string;
}
```

**7. Service: `frontend/src/services/comments.service.ts`** (YENİ DOSYA)

```typescript
import { apiGet, apiPost } from '@/services/api-client';
import type { Comment } from '@/types/api';

export const commentsService = {
  list(workspaceId: number, filters?: { platform?: string; sentiment?: string }) {
    const params = new URLSearchParams({ workspace_id: String(workspaceId) });
    if (filters?.platform) params.set('platform', filters.platform);
    if (filters?.sentiment) params.set('sentiment', filters.sentiment);
    return apiGet<{ comments: Comment[] }>(`/comments?${params}`).then((d) => d.comments);
  },

  create(workspaceId: number, payload: {
    platform: string;
    author: string;
    text: string;
    sentiment?: string;
  }) {
    return apiPost<{ comment: Comment }>('/comments', {
      workspace_id: workspaceId,
      ...payload,
    }).then((d) => d.comment);
  },
};
```

**8. Hook: `frontend/src/hooks/use-comments.ts`** (YENİ DOSYA)

```typescript
import { useQuery } from '@tanstack/react-query';
import { commentsService } from '@/services/comments.service';

export function useComments(
  workspaceId: number | null,
  filters?: { platform?: string; sentiment?: string },
) {
  return useQuery({
    queryKey: ['comments', workspaceId, filters],
    queryFn: () => commentsService.list(workspaceId!, filters),
    enabled: workspaceId !== null,
  });
}
```

**9. Sayfa: `frontend/src/app/(dashboard)/comments/page.tsx`** (YENİ DOSYA)

Tam sayfa implementasyonu:

```typescript
'use client';

import { useState } from 'react';
import { MessageCircle, ThumbsUp, Minus, ThumbsDown } from 'lucide-react';
import { PageHeader } from '@/components/shared/PageHeader';
import { EmptyState } from '@/components/shared/EmptyState';
import { LoadingSpinner } from '@/components/shared/LoadingSpinner';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { useComments } from '@/hooks/use-comments';
import { useWorkspace } from '@/store/workspace-context';
import { de } from '@/lib/i18n/de';
import { cn } from '@/lib/utils';
import type { Comment } from '@/types/api';

const PLATFORMS = ['instagram', 'tiktok', 'facebook', 'linkedin'] as const;
const SENTIMENTS = ['positive', 'neutral', 'negative'] as const;

const sentimentConfig = {
  positive: { label: 'Positiv',  icon: ThumbsUp,   class: 'text-green-500  bg-green-500/10'  },
  neutral:  { label: 'Neutral',  icon: Minus,       class: 'text-yellow-500 bg-yellow-500/10' },
  negative: { label: 'Negativ',  icon: ThumbsDown,  class: 'text-red-500    bg-red-500/10'    },
};

function CommentCard({ comment }: { comment: Comment }) {
  const sentiment = sentimentConfig[comment.sentiment];
  const SentimentIcon = sentiment.icon;

  return (
    <Card>
      <CardContent className="pt-4">
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1 space-y-1.5">
            <div className="flex items-center gap-2 text-xs text-on-surface-variant">
              <span className="font-medium text-on-surface">@{comment.author}</span>
              <span>·</span>
              <span className="capitalize">{comment.platform}</span>
              {comment.commented_at && (
                <>
                  <span>·</span>
                  <span>{new Date(comment.commented_at).toLocaleDateString('de-DE')}</span>
                </>
              )}
            </div>
            <p className="text-sm text-on-surface">{comment.text}</p>
          </div>
          <div className={cn('flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium', sentiment.class)}>
            <SentimentIcon className="h-3.5 w-3.5" />
            <span>{sentiment.label}</span>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

export default function CommentsPage() {
  const { workspaceId } = useWorkspace();
  const [platform, setPlatform] = useState<string | undefined>();
  const [sentiment, setSentiment] = useState<string | undefined>();

  const { data: comments, isLoading } = useComments(workspaceId, { platform, sentiment });

  if (!workspaceId) {
    return <EmptyState title="Kein Workspace ausgewählt" description="" />;
  }

  return (
    <div>
      <PageHeader
        title="Kommentare"
        description="Kommentare und Reaktionen von deinen Social-Media-Kanälen"
      />

      {/* Filter Row */}
      <div className="mb-6 flex flex-wrap gap-2">
        <button
          onClick={() => setPlatform(undefined)}
          className={cn('rounded-full px-3 py-1 text-xs font-medium transition',
            !platform ? 'bg-primary text-on-primary' : 'bg-white/5 text-on-surface-variant hover:bg-white/10')}
        >
          Alle
        </button>
        {PLATFORMS.map((p) => (
          <button
            key={p}
            onClick={() => setPlatform(platform === p ? undefined : p)}
            className={cn('rounded-full px-3 py-1 text-xs font-medium capitalize transition',
              platform === p ? 'bg-primary text-on-primary' : 'bg-white/5 text-on-surface-variant hover:bg-white/10')}
          >
            {p}
          </button>
        ))}
        <span className="mx-2 self-center text-white/20">|</span>
        {SENTIMENTS.map((s) => (
          <button
            key={s}
            onClick={() => setSentiment(sentiment === s ? undefined : s)}
            className={cn('rounded-full px-3 py-1 text-xs font-medium transition',
              sentiment === s
                ? s === 'positive' ? 'bg-green-500/20 text-green-400'
                  : s === 'negative' ? 'bg-red-500/20 text-red-400'
                  : 'bg-yellow-500/20 text-yellow-400'
                : 'bg-white/5 text-on-surface-variant hover:bg-white/10')}
          >
            {sentimentConfig[s].label}
          </button>
        ))}
      </div>

      {isLoading && (
        <div className="flex justify-center py-16">
          <LoadingSpinner />
        </div>
      )}

      {!isLoading && (!comments || comments.length === 0) && (
        <EmptyState
          title="Keine Kommentare"
          description="Wenn deine Social-Media-Konten verbunden sind, erscheinen hier die Kommentare."
        />
      )}

      {comments && comments.length > 0 && (
        <div className="space-y-3">
          {comments.map((comment) => (
            <CommentCard key={comment.id} comment={comment} />
          ))}
        </div>
      )}
    </div>
  );
}
```

**10. i18n: `frontend/src/lib/i18n/de.ts`**

`de.nav.comments` zaten Prompt 1'de eklendi. Ek olarak şunları ekle:
```typescript
comments: {
  title: 'Kommentare',
  description: 'Kommentare und Reaktionen von deinen Social-Media-Kanälen',
  noComments: 'Keine Kommentare',
  noCommentsHint: 'Wenn deine Social-Media-Konten verbunden sind, erscheinen hier die Kommentare.',
  sentiments: {
    positive: 'Positiv',
    neutral: 'Neutral',
    negative: 'Negativ',
  },
},
```

### Amaç:
Mobil uygulamadaki sentiment analizi ile yorum görüntüleme özelliği web'e eklenir.
Şimdilik manuel yorum eklenebilir; ilerleyen fazda Instagram/TikTok API'sinden çekilecek.
```

---

## PROMPT 6 — Dashboard'a KPI Analytics Ekle (Mobil Dashboard ile Aynı)

```
Klicklocal Next.js dashboard sayfasına mobil uygulamadaki gibi KPI analytics kartları ekle.
Mobil: Impressionen, Reichweite (Reach), Engagement Rate gösteriyor.
Mevcut dashboard sadece post istatistiklerini gösteriyor, analytics KPI'ları eksik.

### Mevcut durum:
- `frontend/src/app/(dashboard)/dashboard/page.tsx`:
  Posts stats: total, scheduled, published, failed
  UsageSummaryWidget (AI token kullanımı)
- Backend'de analytics endpoint yok

### Yapılacak değişiklikler:

**1. Backend: `app/Http/Controllers/Api/V1/AnalyticsController.php`** (YENİ DOSYA)

```php
<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function kpi(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        // Yayımlanan post sayısı
        $publishedCount = Post::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'published')
            ->count();

        // Şimdilik simüle edilmiş KPI'lar (gerçek entegrasyon ilerleyen fazda)
        // Platform API'leri bağlandığında gerçek veriler döndürülecek
        $kpi = [
            'impressions'      => $publishedCount * 420,   // ortalama gösterim/post
            'reach'            => (int)($publishedCount * 310),
            'engagement_rate'  => $publishedCount > 0 ? 5.8 : 0.0,
            'published_posts'  => $publishedCount,
            'is_estimated'     => true,  // Frontend'de "tahmini" badge gösterilecek
        ];

        return ApiResponse::success(['kpi' => $kpi]);
    }
}
```

**2. Routes: `routes/api.php`**

workspace.team middleware grubuna ekle (subscription.required olmadan):
```php
Route::get('/analytics/kpi', [AnalyticsController::class, 'kpi']);
```

**3. Frontend Service: `frontend/src/services/analytics.service.ts`** (YENİ DOSYA)

```typescript
import { apiGet } from '@/services/api-client';

export interface KpiData {
  impressions: number;
  reach: number;
  engagement_rate: number;
  published_posts: number;
  is_estimated: boolean;
}

export const analyticsService = {
  kpi(workspaceId: number) {
    return apiGet<{ kpi: KpiData }>(`/analytics/kpi?workspace_id=${workspaceId}`)
      .then((d) => d.kpi);
  },
};
```

**4. Frontend Hook: `frontend/src/hooks/use-analytics.ts`** (YENİ DOSYA)

```typescript
import { useQuery } from '@tanstack/react-query';
import { analyticsService } from '@/services/analytics.service';

export function useKpi(workspaceId: number | null) {
  return useQuery({
    queryKey: ['analytics-kpi', workspaceId],
    queryFn: () => analyticsService.kpi(workspaceId!),
    enabled: workspaceId !== null,
  });
}
```

**5. `frontend/src/app/(dashboard)/dashboard/page.tsx`**

Mevcut 4 post stat kartının ÜSTÜNE 3 KPI kartı ekle:

```typescript
import { useKpi } from '@/hooks/use-analytics';
import { Eye, Users, TrendingUp } from 'lucide-react';

// component içinde:
const kpiQuery = useKpi(workspaceId);

// render'da, post stat kartlarından önce:
{kpiQuery.data && (
  <div className="mb-6">
    <div className="mb-3 flex items-center gap-2">
      <p className="text-sm font-medium text-on-surface-variant">Analytics</p>
      {kpiQuery.data.is_estimated && (
        <span className="rounded-full bg-white/5 px-2 py-0.5 text-xs text-on-surface-variant">
          Geschätzt
        </span>
      )}
    </div>
    <div className="grid gap-4 sm:grid-cols-3">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between pb-2">
          <CardTitle className="text-sm font-medium text-on-surface-variant">Impressionen</CardTitle>
          <Eye className="h-4 w-4 text-primary/70" />
        </CardHeader>
        <CardContent>
          <p className="text-3xl font-semibold">{kpiQuery.data.impressions.toLocaleString('de-DE')}</p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between pb-2">
          <CardTitle className="text-sm font-medium text-on-surface-variant">Reichweite</CardTitle>
          <Users className="h-4 w-4 text-primary/70" />
        </CardHeader>
        <CardContent>
          <p className="text-3xl font-semibold">{kpiQuery.data.reach.toLocaleString('de-DE')}</p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between pb-2">
          <CardTitle className="text-sm font-medium text-on-surface-variant">Engagement</CardTitle>
          <TrendingUp className="h-4 w-4 text-primary/70" />
        </CardHeader>
        <CardContent>
          <p className="text-3xl font-semibold">{kpiQuery.data.engagement_rate.toFixed(1)}%</p>
        </CardContent>
      </Card>
    </div>
  </div>
)}
```

### Amaç:
Dashboard mobil uygulamadaki gibi KPI kartları + post istatistikleri gösterir.
Platform API entegrasyonu hazır olunca `is_estimated: false` döndürülür, gerçek veriler gelir.
```

---

## Uygulama Sırası

```
1. Prompt 1 → Navigation (sidebar) — en az bağımlılık, hızlı görsel değişim
2. Prompt 2 → Register/Onboarding — auth akışı basitleşir
3. Prompt 3 → Billing consolidation — 3 sayfa → 1 sayfa
4. Prompt 4 → Settings expansion — 4 sayfa → 1 sayfa
5. Prompt 5 → Comments sayfası — yeni özellik (backend + frontend)
6. Prompt 6 → Dashboard KPI — yeni özellik (backend + frontend)
```

Her prompt uygulandıktan sonra `npm run build` çalıştır, TypeScript hatası yoksa devam et.
Backend değişikliklerinde `php artisan migrate` ve `php artisan route:cache` çalıştır.
