# Klicklocal Mobile — Sichtbar → Klicklocal Backend Migration

Sichtbar/Appronix mobil uygulamasını Klicklocal backend API'sine bağla.
Tasarım (renkler, fontlar, komponentler) birebir korunur. Sadece auth + veri katmanı değişir.

Çalışma dizini: `mobile/` (D:\NEWxampp\htdocs\klicklocal\mobile)

---

## PROMPT 1 — Proje Konfigürasyonu (package.json, tailwind, babel, metro, env)

```
Klicklocal mobile Expo projesini Sichtbar/Appronix ile aynı dependency set'e yükselt.
NativeWind, React Query, Axios, AsyncStorage, Zod kurulumu. Tasarım renkleri konfigüre et.

Tüm değişiklikler `mobile/` klasöründe yapılacak.

### 1. `mobile/package.json` — tüm içeriği değiştir:

```json
{
  "name": "klicklocal-mobile",
  "version": "1.0.0",
  "private": true,
  "main": "expo-router/entry",
  "scripts": {
    "start": "expo start",
    "android": "expo run:android",
    "ios": "expo run:ios",
    "web": "expo start --web",
    "typecheck": "tsc --noEmit"
  },
  "dependencies": {
    "@expo/vector-icons": "^14.1.0",
    "@react-native-async-storage/async-storage": "^2.2.0",
    "@react-navigation/native": "^7.1.19",
    "@react-navigation/bottom-tabs": "^7.3.14",
    "@tanstack/react-query": "^5.90.10",
    "axios": "^1.13.2",
    "expo": "~54.0.23",
    "expo-linking": "~8.0.12",
    "expo-router": "~6.0.14",
    "expo-status-bar": "~3.0.8",
    "nativewind": "^4.2.1",
    "react": "19.1.0",
    "react-native": "0.81.5",
    "react-native-reanimated": "~4.1.1",
    "react-native-safe-area-context": "~5.6.0",
    "react-native-screens": "~4.16.0",
    "zod": "^4.1.13"
  },
  "devDependencies": {
    "@types/react": "~19.1.10",
    "tailwindcss": "^3.4.18",
    "typescript": "~5.9.2"
  }
}
```

### 2. `mobile/tailwind.config.js` — tüm içeriği değiştir:

```js
/** @type {import('tailwindcss').Config} */
module.exports = {
  presets: [require('nativewind/preset')],
  content: ['./app/**/*.{ts,tsx}', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        primary: '#4FE3C8',
        primaryDark: '#36E0A6',
        accent: '#9B8CF0',
        background: '#070B0A',
        surface: '#0D1412',
        card: '#121B18',
        ink: '#EEF3F1',
        muted: '#8A9795',
        border: '#22322C',
      }
    }
  },
  plugins: []
};
```

### 3. `mobile/babel.config.js` — tüm içeriği değiştir:

```js
module.exports = function (api) {
  api.cache(true);
  return {
    presets: [
      ['babel-preset-expo', { jsxImportSource: 'nativewind' }],
      'nativewind/babel',
    ],
  };
};
```

### 4. `mobile/metro.config.js` — yeni dosya oluştur:

```js
const { getDefaultConfig } = require('expo/metro-config');
const { withNativeWind } = require('nativewind/metro');

const config = getDefaultConfig(__dirname);
module.exports = withNativeWind(config, { input: './global.css' });
```

### 5. `mobile/global.css` — yeni dosya oluştur:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

### 6. `mobile/.env.example` — yeni dosya oluştur:

```
# Klicklocal API base URL (LAN IP when testing on physical device)
EXPO_PUBLIC_API_URL=http://localhost:1981/klicklocal/backend/public/api/v1
```

### 7. `mobile/.env` — yeni dosya oluştur:

```
EXPO_PUBLIC_API_URL=http://localhost:1981/klicklocal/backend/public/api/v1
```

### 8. `mobile/app.json` — `expo.scheme` ve `expo.name` güncelle:

Mevcut `app.json` içinde:
- `"name"` değerini `"Klicklocal"` yap
- `"slug"` değerini `"klicklocal"` yap
- `"scheme"` olarak `"klicklocal"` ekle
- `"backgroundColor"` olarak `"#070B0A"` ekle
- `"userInterfaceStyle"` olarak `"dark"` ekle

### 9. `mobile/tsconfig.json` — paths ekle (eğer yoksa):

```json
{
  "extends": "expo/tsconfig.base",
  "compilerOptions": {
    "strict": true,
    "paths": {
      "@/*": ["./src/*"]
    }
  }
}
```

### Kurulum komutu (çalıştır):

```bash
cd mobile
npm install
```
```

---

## PROMPT 2 — Data Layer: API Client, Types, Services, Auth Provider

```
Klicklocal mobile projesinde Supabase bağımlılıklarını kaldır.
Klicklocal Laravel backend'i ile çalışan tam veri katmanını oluştur.
Sichtbar'ın aynı AuthProvider pattern'ını kullan ama Supabase yerine Klicklocal API.

Klicklocal backend response formatı:
{
  "success": true,
  "message": "...",
  "data": { ... }
}
422 hatalarında: { "success": false, "message": "Validation failed.", "errors": {...} }

### 1. `mobile/src/lib/env.ts` — API URL config (Supabase kaldır):

```typescript
const apiUrl = process.env.EXPO_PUBLIC_API_URL ?? '';

if (!apiUrl) {
  console.warn('EXPO_PUBLIC_API_URL is not set. Set it in .env file.');
}

export const env = {
  apiUrl,
  isConfigured: Boolean(apiUrl),
};
```

### 2. `mobile/src/lib/api-client.ts` — Axios client (Klicklocal için):

```typescript
import axios, { AxiosError } from 'axios';
import { env } from './env';

export const apiClient = axios.create({
  baseURL: env.apiUrl,
  timeout: 20000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

export function setApiToken(token: string | null): void {
  if (token) {
    apiClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  } else {
    delete apiClient.defaults.headers.common['Authorization'];
  }
}

export function setWorkspaceId(workspaceId: number | null): void {
  if (workspaceId) {
    apiClient.defaults.headers.common['X-Workspace-Id'] = String(workspaceId);
  } else {
    delete apiClient.defaults.headers.common['X-Workspace-Id'];
  }
}

// Unwrap Laravel ApiResponse envelope: { success, message, data }
export async function apiGet<T>(path: string): Promise<T> {
  const res = await apiClient.get<{ success: boolean; data: T }>(path);
  return res.data.data;
}

export async function apiPost<T>(path: string, body?: unknown): Promise<T> {
  const res = await apiClient.post<{ success: boolean; data: T }>(path, body);
  return res.data.data;
}

export function getApiErrorMessage(e: unknown): string {
  if (e instanceof AxiosError) {
    const payload = e.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;
    if (payload?.errors) {
      const first = Object.values(payload.errors)[0];
      if (first?.[0]) return first[0];
    }
    return payload?.message ?? e.message;
  }
  if (e instanceof Error) return e.message;
  return 'Ein unbekannter Fehler ist aufgetreten.';
}
```

### 3. `mobile/src/lib/query-client.ts`:

```typescript
import { QueryClient } from '@tanstack/react-query';

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: { staleTime: 30_000, gcTime: 300_000, retry: 1 },
    mutations: { retry: 0 },
  },
});
```

### 4. `mobile/src/types/api.ts`:

```typescript
export interface ApiSuccess<T> {
  success: true;
  data: T;
  message?: string;
}

export interface Post {
  id: number;
  workspace_id: number;
  caption: string;
  status: 'draft' | 'scheduled' | 'published' | 'failed';
  platform?: string;
  scheduled_at?: string | null;
  published_at?: string | null;
  created_at: string;
}

export interface Comment {
  id: number;
  workspace_id: number;
  platform: 'instagram' | 'tiktok' | 'facebook' | 'linkedin';
  author: string;
  text: string;
  sentiment: 'positive' | 'neutral' | 'negative';
  commented_at: string | null;
  created_at: string;
}

export interface SocialAccount {
  id: number;
  platform: 'instagram' | 'tiktok' | 'facebook';
  account_name: string;
  connected: boolean;
  connected_at?: string | null;
}

export interface KpiSnapshot {
  impressions: number;
  reach: number;
  engagement_rate: number;
  published_posts: number;
  is_estimated: boolean;
}

export interface AiGeneration {
  id: number;
  caption: string;
  story_text?: string;
  hashtags: string[];
  call_to_action?: string;
  platform: string;
  content_type: string;
}

export interface Workspace {
  id: number;
  name: string;
  owner_id: number;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  onboarding_completed: boolean;
}
```

### 5. `mobile/src/features/auth/api.ts` — Klicklocal backend:

```typescript
import { apiPost, apiGet } from '@/lib/api-client';
import type { AuthUser, Workspace } from '@/types/api';

export interface LoginResult {
  user: AuthUser;
  token: string;
  onboarding_completed: boolean;
}

export interface RegisterResult {
  user: AuthUser;
  token: string;
  workspace: Workspace;
}

// Step 1: register with email only, get token
async function registerEmail(email: string): Promise<{ user: AuthUser; token: string }> {
  return apiPost('/auth/register-email', { email });
}

// Step 2: complete onboarding with password + business info
async function completeOnboarding(data: {
  password: string;
  password_confirmation: string;
  business_name: string;
  industry: string;
  first_name?: string;
}): Promise<{ user: AuthUser; workspace: Workspace }> {
  return apiPost('/auth/onboarding/complete', data);
}

// Combined register: email-first then complete — used by mobile
export async function register(input: {
  email: string;
  password: string;
  businessName: string;
  industry: string;
}): Promise<RegisterResult> {
  const step1 = await registerEmail(input.email);
  // step1 token is now in apiClient headers (set by AuthProvider before calling this)
  const step2 = await completeOnboarding({
    password: input.password,
    password_confirmation: input.password,
    business_name: input.businessName,
    industry: input.industry,
  });
  return {
    user: step2.user,
    token: step1.token,
    workspace: step2.workspace,
  };
}

export async function login(input: { email: string; password: string }): Promise<LoginResult> {
  return apiPost('/auth/login', input);
}

export async function logout(): Promise<void> {
  await apiPost('/auth/logout');
}

export async function getWorkspaces(): Promise<Workspace[]> {
  const data = await apiGet<{ workspaces: Workspace[] }>('/workspaces');
  return data.workspaces;
}
```

### 6. `mobile/src/features/auth/types.ts`:

```typescript
export interface LoginInput {
  email: string;
  password: string;
}

export interface RegisterInput {
  email: string;
  password: string;
  businessName: string;
  industry: string;
}
```

### 7. `mobile/src/features/auth/schema.ts`:

```typescript
import { z } from 'zod';

export const loginSchema = z.object({
  email: z.string().email('Ungültige E-Mail'),
  password: z.string().min(8, 'Mindestens 8 Zeichen'),
});

export const registerSchema = loginSchema.extend({
  businessName: z.string().min(2, 'Mindestens 2 Zeichen').max(120),
  industry: z.string().min(1, 'Branche wählen'),
});
```

### 8. `mobile/src/features/posts/api.ts`:

```typescript
import { apiGet } from '@/lib/api-client';
import type { Post } from '@/types/api';

export async function listPosts(workspaceId: number): Promise<Post[]> {
  const data = await apiGet<{ posts: Post[] }>(`/posts?workspace_id=${workspaceId}`);
  return data.posts;
}
```

### 9. `mobile/src/features/ai/api.ts`:

```typescript
import { apiPost } from '@/lib/api-client';
import type { AiGeneration } from '@/types/api';

export async function generateContent(
  workspaceId: number,
  payload: { prompt?: string; platform?: string; content_type?: string }
): Promise<AiGeneration> {
  const data = await apiPost<{ generation: AiGeneration }>('/ai/generate', {
    workspace_id: workspaceId,
    platform: payload.platform ?? 'instagram',
    content_type: payload.content_type ?? 'post',
    prompt: payload.prompt ?? '',
  });
  return data.generation;
}
```

### 10. `mobile/src/features/comments/api.ts`:

```typescript
import { apiGet } from '@/lib/api-client';
import type { Comment } from '@/types/api';

export async function listComments(
  workspaceId: number,
  filters?: { platform?: string; sentiment?: string }
): Promise<Comment[]> {
  const params = new URLSearchParams({ workspace_id: String(workspaceId) });
  if (filters?.platform) params.set('platform', filters.platform);
  if (filters?.sentiment) params.set('sentiment', filters.sentiment);
  const data = await apiGet<{ comments: Comment[] }>(`/comments?${params}`);
  return data.comments;
}
```

### 11. `mobile/src/features/social/api.ts`:

```typescript
import { apiGet } from '@/lib/api-client';
import type { SocialAccount } from '@/types/api';

export async function listSocialAccounts(workspaceId: number): Promise<SocialAccount[]> {
  // Get status for each platform and combine
  const platforms: SocialAccount['platform'][] = ['instagram', 'tiktok'];
  const results: SocialAccount[] = [];

  for (const platform of platforms) {
    try {
      const data = await apiGet<{ connected: boolean; account_name?: string; connected_at?: string }>(
        `/social-accounts/${platform}/status?workspace_id=${workspaceId}`
      );
      results.push({
        id: results.length + 1,
        platform,
        account_name: data.account_name ?? `@${platform}`,
        connected: data.connected,
        connected_at: data.connected_at,
      });
    } catch {
      results.push({ id: results.length + 1, platform, account_name: platform, connected: false });
    }
  }
  return results;
}
```

### 12. `mobile/src/features/analytics/api.ts`:

```typescript
import { apiGet } from '@/lib/api-client';
import type { KpiSnapshot } from '@/types/api';

export async function getDashboardAnalytics(workspaceId: number): Promise<KpiSnapshot> {
  return apiGet<KpiSnapshot>(`/analytics/kpi?workspace_id=${workspaceId}`);
}
```

### 13. `mobile/src/providers/auth-provider.tsx` — Klicklocal backend için adapte et:

```typescript
import AsyncStorage from '@react-native-async-storage/async-storage';
import { PropsWithChildren, createContext, useContext, useEffect, useMemo, useState } from 'react';
import { login as loginApi, logout as logoutApi, register as registerApi, getWorkspaces } from '@/features/auth/api';
import { LoginInput, RegisterInput } from '@/features/auth/types';
import { setApiToken, setWorkspaceId } from '@/lib/api-client';

const SESSION_KEY = 'klicklocal.session';

interface SessionState {
  token: string;
  workspaceId: number;
  user: {
    id: number;
    name: string;
    email: string;
    onboarding_completed: boolean;
  };
}

interface AuthContextValue {
  isBootstrapping: boolean;
  session: SessionState | null;
  isAuthenticated: boolean;
  login: (input: LoginInput) => Promise<void>;
  register: (input: RegisterInput) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: PropsWithChildren) {
  const [isBootstrapping, setIsBootstrapping] = useState(true);
  const [session, setSession] = useState<SessionState | null>(null);

  useEffect(() => {
    const bootstrap = async () => {
      try {
        const raw = await AsyncStorage.getItem(SESSION_KEY);
        if (!raw) return;
        const parsed = JSON.parse(raw) as SessionState;
        // Restore token + workspaceId to axios headers
        setApiToken(parsed.token);
        setWorkspaceId(parsed.workspaceId);
        setSession(parsed);
      } catch {
        await AsyncStorage.removeItem(SESSION_KEY);
        setApiToken(null);
        setWorkspaceId(null);
        setSession(null);
      } finally {
        setIsBootstrapping(false);
      }
    };
    void bootstrap();
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      isBootstrapping,
      session,
      isAuthenticated: Boolean(session?.token),
      login: async (input) => {
        const result = await loginApi(input);
        setApiToken(result.token);
        // Fetch workspaces to get workspace_id
        const workspaces = await getWorkspaces();
        const workspaceId = workspaces[0]?.id ?? 0;
        setWorkspaceId(workspaceId);
        const next: SessionState = { token: result.token, workspaceId, user: result.user };
        setSession(next);
        await AsyncStorage.setItem(SESSION_KEY, JSON.stringify(next));
      },
      register: async (input) => {
        // register sets token into apiClient headers internally
        const { email, password, businessName, industry } = input as RegisterInput;
        // Step 1: get temporary token from register-email
        const { default: axios } = await import('@/lib/api-client');
        // We need the token from step 1 before calling complete — do it inline
        const { apiPost: post, setApiToken: sat } = await import('@/lib/api-client');
        const step1 = await post<{ user: { id: number; name: string; email: string; onboarding_completed: boolean }; token: string }>(
          '/auth/register-email', { email }
        );
        sat(step1.token);
        const step2 = await post<{ user: { id: number; name: string; email: string; onboarding_completed: boolean }; workspace: { id: number; name: string; owner_id: number } }>(
          '/auth/onboarding/complete',
          { password, password_confirmation: password, business_name: businessName, industry, first_name: '' }
        );
        const workspaceId = step2.workspace.id;
        setWorkspaceId(workspaceId);
        const next: SessionState = { token: step1.token, workspaceId, user: step2.user };
        setSession(next);
        await AsyncStorage.setItem(SESSION_KEY, JSON.stringify(next));
      },
      logout: async () => {
        try { await logoutApi(); } catch { /* ignore */ }
        setApiToken(null);
        setWorkspaceId(null);
        setSession(null);
        await AsyncStorage.removeItem(SESSION_KEY);
      },
    }),
    [isBootstrapping, session]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
```

**Not:** `auth-provider.tsx`'deki register fonksiyonundaki dynamic import karmaşıklığını önlemek için
şu şekilde düzelt — register fonksiyonunu daha temiz hale getir:

```typescript
register: async (input) => {
  const ri = input as RegisterInput;
  const step1 = await apiPost<{ user: AuthUser; token: string }>(
    '/auth/register-email', { email: ri.email }
  );
  setApiToken(step1.token);  // token'ı hemen set et ki complete çalışsın
  const step2 = await apiPost<{ user: AuthUser; workspace: Workspace }>(
    '/auth/onboarding/complete', {
      password: ri.password,
      password_confirmation: ri.password,
      business_name: ri.businessName,
      industry: ri.industry,
      first_name: '',
    }
  );
  const wId = step2.workspace.id;
  setWorkspaceId(wId);
  const next: SessionState = { token: step1.token, workspaceId: wId, user: step2.user };
  setSession(next);
  await AsyncStorage.setItem(SESSION_KEY, JSON.stringify(next));
},
```

Yukarıdaki temiz versiyonu kullan. `apiPost` ve tip import'larını dosyanın başına ekle:
`import { apiPost, setApiToken, setWorkspaceId } from '@/lib/api-client';`
`import type { AuthUser, Workspace } from '@/types/api';`

### 14. `mobile/src/providers/theme-provider.tsx`:

```typescript
import { PropsWithChildren } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';
export function ThemeProvider({ children }: PropsWithChildren) {
  return <SafeAreaProvider>{children}</SafeAreaProvider>;
}
```

### 15. `mobile/src/providers/app-provider.tsx`:

```typescript
import { QueryClientProvider } from '@tanstack/react-query';
import { PropsWithChildren } from 'react';
import { queryClient } from '@/lib/query-client';
import { AuthProvider } from './auth-provider';
import { ThemeProvider } from './theme-provider';

export function AppProvider({ children }: PropsWithChildren) {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <AuthProvider>{children}</AuthProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}
```

### 16. `mobile/src/utils/constants.ts`:

```typescript
export const QUERY_KEYS = {
  dashboard: ['dashboard'],
  posts: ['posts'],
  comments: ['comments'],
  socialAccounts: ['social-accounts'],
  aiGenerate: ['ai-generate'],
} as const;

export const INDUSTRIES = [
  'Restaurant / Café',
  'Einzelhandel',
  'Beauty & Wellness',
  'Fitness & Sport',
  'Handwerk',
  'Dienstleistungen',
  'Medizin / Gesundheit',
  'Immobilien',
  'Mode & Lifestyle',
  'Sonstiges',
] as const;
```
```

---

## PROMPT 3 — UI Design System (Design Komponentleri — Birebir Sichtbar)

```
Klicklocal mobile projesinde Sichtbar/Appronix'in tasarım komponent sistemini oluştur.
Renkler, şekiller, fontlar birebir aynı kalacak. Hiçbir tasarım değişikliği yok.

Tüm dosyalar `mobile/src/ui/` altına yazılacak.

### 1. `mobile/src/ui/auth-primitives.tsx` — Birebir kopyala:

```typescript
import { Ionicons } from '@expo/vector-icons';
import { Pressable, Text, TextInput, View } from 'react-native';

interface PrimaryButtonProps {
  label: string;
  onPress: () => void;
  disabled?: boolean;
  loading?: boolean;
}

interface OutlineButtonProps {
  label: string;
  onPress: () => void;
  iconName?: keyof typeof Ionicons.glyphMap;
}

interface PillFieldProps {
  value: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  iconName: keyof typeof Ionicons.glyphMap;
  secureTextEntry?: boolean;
}

export function AuthBackground({ children }: { children: React.ReactNode }) {
  return (
    <View className="flex-1 bg-slate-950">
      <View className="absolute inset-0 bg-emerald-500/10" />
      <View className="absolute -left-24 top-0 h-72 w-72 rounded-full bg-emerald-400/25" />
      <View className="absolute -bottom-24 -right-20 h-72 w-72 rounded-full bg-indigo-500/25" />
      <View className="absolute inset-0 bg-slate-950/80" />
      {children}
    </View>
  );
}

export function AppGlyph({ size = 96 }: { size?: number }) {
  return (
    <View
      className="items-center justify-center rounded-[28px] bg-primary"
      style={{ width: size, height: size, shadowColor: '#4FE3C8', shadowOpacity: 0.35, shadowRadius: 20, elevation: 8 }}
    >
      <Ionicons name="sparkles-outline" size={size * 0.45} color="#04140D" />
    </View>
  );
}

export function AuthPrimaryButton({ label, onPress, disabled, loading }: PrimaryButtonProps) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled || loading}
      className={`h-14 items-center justify-center rounded-full bg-primary ${(disabled || loading) ? 'opacity-60' : ''}`}
    >
      <Text className="text-[18px] font-extrabold text-[#04140d]">
        {loading ? 'Bitte warten…' : label}
      </Text>
    </Pressable>
  );
}

export function AuthOutlineButton({ label, onPress, iconName }: OutlineButtonProps) {
  return (
    <Pressable
      onPress={onPress}
      className="h-14 flex-row items-center justify-center gap-2 rounded-full border border-slate-700 bg-slate-900/80"
    >
      {iconName ? <Ionicons name={iconName} size={18} color="#FFFFFF" /> : null}
      <Text className="text-[18px] font-bold text-white">{label}</Text>
    </Pressable>
  );
}

export function PillField({ value, onChangeText, placeholder, iconName, secureTextEntry }: PillFieldProps) {
  return (
    <View className="h-14 flex-row items-center rounded-full border border-slate-700 bg-slate-900/80 px-5">
      <Ionicons name={iconName} size={18} color="#7D8A8B" />
      <TextInput
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor="#6B7A78"
        secureTextEntry={secureTextEntry}
        autoCapitalize="none"
        className="ml-3 flex-1 text-[16px] text-white"
      />
    </View>
  );
}

export function ErrorMessage({ message }: { message: string }) {
  return (
    <View className="rounded-2xl bg-red-500/15 px-4 py-3">
      <Text className="text-sm text-red-400">{message}</Text>
    </View>
  );
}
```

### 2. `mobile/src/ui/button.tsx` — Birebir kopyala:

```typescript
import { ActivityIndicator, Pressable, Text } from 'react-native';

interface ButtonProps {
  label: string;
  onPress: () => void;
  disabled?: boolean;
  loading?: boolean;
  variant?: 'primary' | 'secondary';
}

export function Button({ label, onPress, disabled, loading, variant = 'primary' }: ButtonProps) {
  const palette = variant === 'primary'
    ? 'bg-primary border-primary'
    : 'bg-slate-900 border-slate-700';

  return (
    <Pressable
      onPress={onPress}
      disabled={disabled || loading}
      className={`h-12 flex-row items-center justify-center gap-2 rounded-full border ${palette} ${(disabled || loading) ? 'opacity-60' : ''}`}
    >
      {loading && <ActivityIndicator size="small" color={variant === 'primary' ? '#04140D' : '#4FE3C8'} />}
      <Text className={`text-base font-semibold ${variant === 'primary' ? 'text-[#04140d]' : 'text-white'}`}>
        {label}
      </Text>
    </Pressable>
  );
}
```

### 3. `mobile/src/ui/card.tsx` — Birebir kopyala:

```typescript
import { PropsWithChildren } from 'react';
import { View } from 'react-native';

export function Card({ children }: PropsWithChildren) {
  return <View className="rounded-3xl border border-slate-800 bg-slate-900 p-4">{children}</View>;
}
```

### 4. `mobile/src/ui/header.tsx` — Birebir kopyala:

```typescript
import { Text, View } from 'react-native';

interface HeaderProps {
  title: string;
  subtitle?: string;
}

export function Header({ title, subtitle }: HeaderProps) {
  return (
    <View className="gap-1">
      <Text className="text-3xl font-bold text-white">{title}</Text>
      {subtitle ? <Text className="text-sm text-slate-300">{subtitle}</Text> : null}
    </View>
  );
}
```

### 5. `mobile/src/ui/input.tsx` — Birebir kopyala:

```typescript
import { Text, TextInput, View } from 'react-native';

interface InputProps {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder?: string;
  secureTextEntry?: boolean;
}

export function Input({ label, value, onChangeText, placeholder, secureTextEntry }: InputProps) {
  return (
    <View className="gap-2">
      <Text className="text-sm font-medium text-slate-300">{label}</Text>
      <TextInput
        className="h-12 rounded-2xl border border-slate-700 bg-slate-900 px-4 text-base text-white"
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor="#758381"
        secureTextEntry={secureTextEntry}
        autoCapitalize="none"
      />
    </View>
  );
}
```

### 6. `mobile/src/ui/loader.tsx` — Birebir kopyala:

```typescript
import { ActivityIndicator, View } from 'react-native';

export function Loader() {
  return (
    <View className="flex-1 items-center justify-center bg-slate-950">
      <ActivityIndicator size="large" color="#4FE3C8" />
    </View>
  );
}
```

### 7. `mobile/src/ui/select-chips.tsx` — YENİ (Register için sektör seçim):

```typescript
import { ScrollView, Text, TouchableOpacity, View } from 'react-native';

interface SelectChipsProps {
  options: readonly string[];
  value: string;
  onChange: (value: string) => void;
  label: string;
}

export function SelectChips({ options, value, onChange, label }: SelectChipsProps) {
  return (
    <View className="gap-2">
      <Text className="text-sm font-medium text-slate-300">{label}</Text>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} className="-mx-1">
        <View className="flex-row flex-wrap gap-2 px-1">
          {options.map((opt) => (
            <TouchableOpacity
              key={opt}
              onPress={() => onChange(opt)}
              className={`rounded-full border px-4 py-2 ${
                value === opt
                  ? 'border-primary bg-primary/20'
                  : 'border-slate-700 bg-slate-900'
              }`}
            >
              <Text className={`text-sm font-medium ${value === opt ? 'text-primary' : 'text-slate-300'}`}>
                {opt}
              </Text>
            </TouchableOpacity>
          ))}
        </View>
      </ScrollView>
    </View>
  );
}
```
```

---

## PROMPT 4 — Auth Ekranları (Login + Register)

```
Klicklocal mobile projesinde login ve register ekranlarını oluştur.
Tasarım: Sichtbar/Appronix ile birebir aynı (AuthBackground + AppGlyph + PillField + AuthPrimaryButton).
Backend: Klicklocal Laravel API (register-email + onboarding/complete; login endpoint).

### App Router Yapısı (expo-router):

```
app/
  _layout.tsx        ← Root layout (AppProvider + auth routing)
  (auth)/
    _layout.tsx      ← Auth Stack layout
    login.tsx        ← Login ekranı
    register.tsx     ← Register ekranı
  (tabs)/
    _layout.tsx      ← Tab navigator (Prompt 5'te)
    index.tsx        ← Dashboard (Prompt 5'te)
    ...
```

### 1. `mobile/app/_layout.tsx` — Root layout:

```typescript
import '../global.css';
import { Stack } from 'expo-router';
import { AppProvider } from '@/providers/app-provider';
import { useAuth } from '@/providers/auth-provider';
import { Loader } from '@/ui/loader';
import { useEffect } from 'react';
import { useRouter, useSegments } from 'expo-router';

function RootNavigator() {
  const { isBootstrapping, isAuthenticated } = useAuth();
  const segments = useSegments();
  const router = useRouter();

  useEffect(() => {
    if (isBootstrapping) return;

    const inAuthGroup = segments[0] === '(auth)';

    if (!isAuthenticated && !inAuthGroup) {
      router.replace('/(auth)/login');
    } else if (isAuthenticated && inAuthGroup) {
      router.replace('/(tabs)');
    }
  }, [isBootstrapping, isAuthenticated, segments]);

  if (isBootstrapping) return <Loader />;

  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="(auth)" />
      <Stack.Screen name="(tabs)" />
    </Stack>
  );
}

export default function RootLayout() {
  return (
    <AppProvider>
      <RootNavigator />
    </AppProvider>
  );
}
```

### 2. `mobile/app/(auth)/_layout.tsx`:

```typescript
import { Stack } from 'expo-router';

export default function AuthLayout() {
  return <Stack screenOptions={{ headerShown: false }} />;
}
```

### 3. `mobile/app/(auth)/login.tsx` — Login ekranı:

```typescript
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useAuth } from '@/providers/auth-provider';
import {
  AuthBackground, AppGlyph, PillField,
  AuthPrimaryButton, AuthOutlineButton, ErrorMessage
} from '@/ui/auth-primitives';
import { getApiErrorMessage } from '@/lib/api-client';

export default function LoginScreen() {
  const router = useRouter();
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleLogin() {
    if (!email.trim() || !password.trim()) {
      setError('E-Mail und Passwort sind erforderlich.');
      return;
    }
    setError('');
    setLoading(true);
    try {
      await login({ email: email.trim().toLowerCase(), password });
    } catch (e) {
      setError(getApiErrorMessage(e));
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthBackground>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        className="flex-1"
      >
        <ScrollView
          contentContainerStyle={{ flexGrow: 1 }}
          keyboardShouldPersistTaps="handled"
        >
          <View className="flex-1 justify-center px-6 py-12">
            {/* Logo */}
            <View className="mb-10 items-center">
              <AppGlyph size={80} />
              <Text className="mt-4 text-3xl font-bold text-white">Klicklocal</Text>
              <Text className="mt-1 text-sm text-slate-400">Social Media für Lokalbetriebe</Text>
            </View>

            {/* Form */}
            <View className="gap-4">
              {error ? <ErrorMessage message={error} /> : null}

              <PillField
                value={email}
                onChangeText={setEmail}
                placeholder="E-Mail-Adresse"
                iconName="mail-outline"
              />
              <PillField
                value={password}
                onChangeText={setPassword}
                placeholder="Passwort"
                iconName="lock-closed-outline"
                secureTextEntry
              />

              <View className="mt-2">
                <AuthPrimaryButton
                  label="Anmelden"
                  onPress={handleLogin}
                  loading={loading}
                  disabled={loading}
                />
              </View>
            </View>

            {/* Footer */}
            <View className="mt-8">
              <AuthOutlineButton
                label="Noch kein Konto? Registrieren"
                onPress={() => router.push('/(auth)/register')}
              />
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </AuthBackground>
  );
}
```

### 4. `mobile/app/(auth)/register.tsx` — Register ekranı:

```typescript
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useAuth } from '@/providers/auth-provider';
import {
  AuthBackground, AppGlyph, PillField,
  AuthPrimaryButton, AuthOutlineButton, ErrorMessage
} from '@/ui/auth-primitives';
import { SelectChips } from '@/ui/select-chips';
import { INDUSTRIES } from '@/utils/constants';
import { getApiErrorMessage } from '@/lib/api-client';

export default function RegisterScreen() {
  const router = useRouter();
  const { register } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [businessName, setBusinessName] = useState('');
  const [industry, setIndustry] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleRegister() {
    if (!email.trim() || !password.trim() || !businessName.trim() || !industry) {
      setError('Bitte alle Felder ausfüllen.');
      return;
    }
    if (password.length < 8) {
      setError('Das Passwort muss mindestens 8 Zeichen lang sein.');
      return;
    }
    setError('');
    setLoading(true);
    try {
      await register({
        email: email.trim().toLowerCase(),
        password,
        businessName: businessName.trim(),
        industry,
      });
    } catch (e) {
      setError(getApiErrorMessage(e));
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthBackground>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        className="flex-1"
      >
        <ScrollView
          contentContainerStyle={{ flexGrow: 1 }}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <View className="flex-1 justify-center px-6 py-12">
            {/* Logo */}
            <View className="mb-8 items-center">
              <AppGlyph size={72} />
              <Text className="mt-4 text-2xl font-bold text-white">Konto erstellen</Text>
              <Text className="mt-1 text-sm text-slate-400">Kostenlos starten</Text>
            </View>

            {/* Form */}
            <View className="gap-4">
              {error ? <ErrorMessage message={error} /> : null}

              <PillField
                value={email}
                onChangeText={setEmail}
                placeholder="E-Mail-Adresse"
                iconName="mail-outline"
              />
              <PillField
                value={password}
                onChangeText={setPassword}
                placeholder="Passwort (min. 8 Zeichen)"
                iconName="lock-closed-outline"
                secureTextEntry
              />
              <PillField
                value={businessName}
                onChangeText={setBusinessName}
                placeholder="Name deines Betriebs"
                iconName="business-outline"
              />

              <SelectChips
                label="Branche"
                options={INDUSTRIES}
                value={industry}
                onChange={setIndustry}
              />

              <View className="mt-2">
                <AuthPrimaryButton
                  label="Registrieren"
                  onPress={handleRegister}
                  loading={loading}
                  disabled={loading || !industry}
                />
              </View>
            </View>

            {/* Footer */}
            <View className="mt-6">
              <AuthOutlineButton
                label="Bereits registriert? Anmelden"
                onPress={() => router.back()}
              />
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </AuthBackground>
  );
}
```
```

---

## PROMPT 5 — Tab Navigator + Feature Ekranları (Dashboard, Posts, AI, Social, Comments)

```
Klicklocal mobile projesinde 5 tab'lı ana navigasyonu ve tüm özellik ekranlarını oluştur.
Tasarım: Sichtbar/Appronix ile birebir (slate-950 bg, primary #4FE3C8, Card, Header, Loader).

### 1. `mobile/app/(tabs)/_layout.tsx` — 5 tab navigator:

```typescript
import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';

export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarStyle: {
          backgroundColor: '#0D1412',
          borderTopColor: '#22322C',
          borderTopWidth: 1,
        },
        tabBarActiveTintColor: '#4FE3C8',
        tabBarInactiveTintColor: '#8A9795',
        tabBarLabelStyle: { fontSize: 11, fontWeight: '600' },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Dashboard',
          tabBarIcon: ({ color, size }) => <Ionicons name="grid-outline" size={size} color={color} />,
        }}
      />
      <Tabs.Screen
        name="posts"
        options={{
          title: 'Beiträge',
          tabBarIcon: ({ color, size }) => <Ionicons name="document-text-outline" size={size} color={color} />,
        }}
      />
      <Tabs.Screen
        name="ai"
        options={{
          title: 'KI Studio',
          tabBarIcon: ({ color, size }) => <Ionicons name="sparkles-outline" size={size} color={color} />,
        }}
      />
      <Tabs.Screen
        name="social"
        options={{
          title: 'Social',
          tabBarIcon: ({ color, size }) => <Ionicons name="share-social-outline" size={size} color={color} />,
        }}
      />
      <Tabs.Screen
        name="comments"
        options={{
          title: 'Kommentare',
          tabBarIcon: ({ color, size }) => <Ionicons name="chatbubbles-outline" size={size} color={color} />,
        }}
      />
    </Tabs>
  );
}
```

### 2. `mobile/app/(tabs)/index.tsx` — Dashboard:

```typescript
import { useQuery } from '@tanstack/react-query';
import { ScrollView, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useAuth } from '@/providers/auth-provider';
import { getDashboardAnalytics } from '@/features/analytics/api';
import { Header } from '@/ui/header';
import { Card } from '@/ui/card';
import { Loader } from '@/ui/loader';

interface KpiCardProps {
  label: string;
  value: string | number;
  icon: string;
}

function KpiCard({ label, value, icon }: KpiCardProps) {
  return (
    <Card>
      <Text className="mb-1 text-2xl">{icon}</Text>
      <Text className="text-2xl font-bold text-white">{value}</Text>
      <Text className="mt-1 text-xs text-slate-400">{label}</Text>
    </Card>
  );
}

export default function DashboardScreen() {
  const { session } = useAuth();
  const workspaceId = session?.workspaceId ?? 0;

  const { data: kpi, isLoading } = useQuery({
    queryKey: ['analytics-kpi', workspaceId],
    queryFn: () => getDashboardAnalytics(workspaceId),
    enabled: workspaceId > 0,
  });

  if (isLoading) return <Loader />;

  return (
    <SafeAreaView className="flex-1 bg-slate-950">
      <ScrollView className="flex-1" contentContainerStyle={{ padding: 20, gap: 16 }}>
        <Header
          title="Dashboard"
          subtitle={session?.user?.name ? `Hallo, ${session.user.name}` : 'Willkommen zurück'}
        />

        {/* KPI Cards */}
        <View className="mt-4 grid grid-cols-2 gap-3">
          <View className="grid grid-cols-2 gap-3 flex-row flex-wrap">
            <View className="flex-1 min-w-[45%]">
              <KpiCard
                label="Impressionen"
                value={kpi?.impressions?.toLocaleString('de-DE') ?? '—'}
                icon="👁️"
              />
            </View>
            <View className="flex-1 min-w-[45%]">
              <KpiCard
                label="Reichweite"
                value={kpi?.reach?.toLocaleString('de-DE') ?? '—'}
                icon="👥"
              />
            </View>
            <View className="flex-1 min-w-[45%]">
              <KpiCard
                label="Engagement"
                value={kpi ? `${kpi.engagement_rate.toFixed(1)}%` : '—'}
                icon="📈"
              />
            </View>
            <View className="flex-1 min-w-[45%]">
              <KpiCard
                label="Veröffentlicht"
                value={kpi?.published_posts ?? '—'}
                icon="✅"
              />
            </View>
          </View>
        </View>

        {kpi?.is_estimated && (
          <Text className="text-center text-xs text-slate-500">
            * Geschätzte Werte. Echte Daten nach Social-Verknüpfung.
          </Text>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}
```

### 3. `mobile/app/(tabs)/posts.tsx` — Posts listesi:

```typescript
import { useQuery } from '@tanstack/react-query';
import { FlatList, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useAuth } from '@/providers/auth-provider';
import { listPosts } from '@/features/posts/api';
import { Header } from '@/ui/header';
import { Card } from '@/ui/card';
import { Loader } from '@/ui/loader';
import type { Post } from '@/types/api';

const STATUS_COLORS: Record<Post['status'], string> = {
  draft: '#8A9795',
  scheduled: '#4FE3C8',
  published: '#36E0A6',
  failed: '#F87171',
};

const STATUS_LABELS: Record<Post['status'], string> = {
  draft: 'Entwurf',
  scheduled: 'Geplant',
  published: 'Veröffentlicht',
  failed: 'Fehlgeschlagen',
};

function PostCard({ post }: { post: Post }) {
  return (
    <Card>
      <View className="flex-row items-start justify-between gap-3">
        <Text className="flex-1 text-sm text-slate-200 leading-5" numberOfLines={3}>
          {post.caption || 'Kein Inhalt'}
        </Text>
        <View
          className="rounded-full px-2.5 py-1"
          style={{ backgroundColor: `${STATUS_COLORS[post.status]}20` }}
        >
          <Text className="text-xs font-semibold" style={{ color: STATUS_COLORS[post.status] }}>
            {STATUS_LABELS[post.status]}
          </Text>
        </View>
      </View>
      {post.platform && (
        <Text className="mt-2 text-xs capitalize text-slate-500">{post.platform}</Text>
      )}
      {post.scheduled_at && (
        <Text className="mt-1 text-xs text-slate-600">
          📅 {new Date(post.scheduled_at).toLocaleDateString('de-DE')}
        </Text>
      )}
    </Card>
  );
}

export default function PostsScreen() {
  const { session } = useAuth();
  const workspaceId = session?.workspaceId ?? 0;

  const { data: posts, isLoading } = useQuery({
    queryKey: ['posts', workspaceId],
    queryFn: () => listPosts(workspaceId),
    enabled: workspaceId > 0,
  });

  if (isLoading) return <Loader />;

  return (
    <SafeAreaView className="flex-1 bg-slate-950">
      <View className="px-5 pt-5 pb-3">
        <Header title="Beiträge" subtitle={`${posts?.length ?? 0} Einträge`} />
      </View>
      <FlatList
        data={posts ?? []}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <View className="px-5 pb-3">
            <PostCard post={item} />
          </View>
        )}
        ListEmptyComponent={
          <View className="items-center px-5 py-16">
            <Text className="text-5xl mb-4">✍️</Text>
            <Text className="text-center text-base font-semibold text-white">Noch keine Beiträge</Text>
            <Text className="mt-2 text-center text-sm text-slate-400">
              Erstelle deinen ersten Beitrag im KI Studio.
            </Text>
          </View>
        }
        contentContainerStyle={{ paddingBottom: 24 }}
      />
    </SafeAreaView>
  );
}
```

### 4. `mobile/app/(tabs)/ai.tsx` — AI Content Generator:

```typescript
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useMutation } from '@tanstack/react-query';
import { useAuth } from '@/providers/auth-provider';
import { generateContent } from '@/features/ai/api';
import { Header } from '@/ui/header';
import { Card } from '@/ui/card';
import { Button } from '@/ui/button';
import { ErrorMessage } from '@/ui/auth-primitives';
import { getApiErrorMessage } from '@/lib/api-client';
import type { AiGeneration } from '@/types/api';

const PLATFORMS = ['instagram', 'tiktok', 'facebook'] as const;
const PLATFORM_LABELS: Record<string, string> = {
  instagram: 'Instagram', tiktok: 'TikTok', facebook: 'Facebook'
};

export default function AiScreen() {
  const { session } = useAuth();
  const workspaceId = session?.workspaceId ?? 0;

  const [prompt, setPrompt] = useState('');
  const [platform, setPlatform] = useState<string>('instagram');
  const [result, setResult] = useState<AiGeneration | null>(null);
  const [error, setError] = useState('');

  const mutation = useMutation({
    mutationFn: () => generateContent(workspaceId, { prompt, platform }),
    onSuccess: (data) => { setResult(data); setError(''); },
    onError: (e) => setError(getApiErrorMessage(e)),
  });

  return (
    <SafeAreaView className="flex-1 bg-slate-950">
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} className="flex-1">
        <ScrollView className="flex-1" contentContainerStyle={{ padding: 20, gap: 16 }} keyboardShouldPersistTaps="handled">
          <Header title="KI Studio" subtitle="Content mit KI erstellen" />

          {/* Platform selector */}
          <View className="flex-row gap-2 flex-wrap mt-2">
            {PLATFORMS.map((p) => (
              <TouchableOpacity
                key={p}
                onPress={() => setPlatform(p)}
                className={`rounded-full border px-4 py-2 ${
                  platform === p ? 'border-primary bg-primary/20' : 'border-slate-700 bg-slate-900'
                }`}
              >
                <Text className={`text-sm font-semibold ${platform === p ? 'text-primary' : 'text-slate-400'}`}>
                  {PLATFORM_LABELS[p]}
                </Text>
              </TouchableOpacity>
            ))}
          </View>

          {/* Prompt input */}
          <View className="gap-2">
            <Text className="text-sm font-medium text-slate-300">Dein Wunsch (optional)</Text>
            <TextInput
              className="min-h-[100px] rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-base text-white"
              value={prompt}
              onChangeText={setPrompt}
              placeholder="z.B. Mittagsangebot für heute, neues Produkt, Saisonstart..."
              placeholderTextColor="#758381"
              multiline
              textAlignVertical="top"
            />
          </View>

          {error ? <ErrorMessage message={error} /> : null}

          <Button
            label={mutation.isPending ? 'Erstelle Inhalt…' : '✨ Inhalt erstellen'}
            onPress={() => mutation.mutate()}
            disabled={mutation.isPending || workspaceId === 0}
            loading={mutation.isPending}
          />

          {/* Result */}
          {result && (
            <View className="gap-3 mt-2">
              <Text className="text-base font-semibold text-primary">Ergebnis</Text>

              <Card>
                <Text className="text-xs text-slate-500 mb-1">Caption</Text>
                <Text className="text-sm text-white leading-6">{result.caption}</Text>
              </Card>

              {result.hashtags?.length > 0 && (
                <Card>
                  <Text className="text-xs text-slate-500 mb-2">Hashtags</Text>
                  <Text className="text-sm text-primary leading-6">{result.hashtags.join(' ')}</Text>
                </Card>
              )}

              {result.call_to_action && (
                <Card>
                  <Text className="text-xs text-slate-500 mb-1">Call to Action</Text>
                  <Text className="text-sm text-white">{result.call_to_action}</Text>
                </Card>
              )}
            </View>
          )}
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
```

### 5. `mobile/app/(tabs)/social.tsx` — Social Accounts:

```typescript
import { useQuery } from '@tanstack/react-query';
import { Linking, ScrollView, Text, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '@/providers/auth-provider';
import { listSocialAccounts } from '@/features/social/api';
import { Header } from '@/ui/header';
import { Card } from '@/ui/card';
import { Loader } from '@/ui/loader';
import type { SocialAccount } from '@/types/api';

const PLATFORM_ICONS: Record<SocialAccount['platform'], keyof typeof Ionicons.glyphMap> = {
  instagram: 'logo-instagram',
  tiktok: 'musical-notes-outline',
  facebook: 'logo-facebook',
};

const PLATFORM_COLORS: Record<SocialAccount['platform'], string> = {
  instagram: '#E1306C',
  tiktok: '#4FE3C8',
  facebook: '#1877F2',
};

function SocialCard({ account }: { account: SocialAccount }) {
  return (
    <Card>
      <View className="flex-row items-center gap-4">
        <View
          className="h-12 w-12 items-center justify-center rounded-2xl"
          style={{ backgroundColor: `${PLATFORM_COLORS[account.platform]}20` }}
        >
          <Ionicons
            name={PLATFORM_ICONS[account.platform]}
            size={24}
            color={PLATFORM_COLORS[account.platform]}
          />
        </View>
        <View className="flex-1">
          <Text className="text-base font-semibold capitalize text-white">{account.platform}</Text>
          <Text className="text-sm text-slate-400">
            {account.connected ? account.account_name : 'Nicht verbunden'}
          </Text>
        </View>
        <View
          className={`rounded-full px-3 py-1 ${account.connected ? 'bg-primary/15' : 'bg-slate-800'}`}
        >
          <Text className={`text-xs font-semibold ${account.connected ? 'text-primary' : 'text-slate-500'}`}>
            {account.connected ? 'Verbunden' : 'Verbinden'}
          </Text>
        </View>
      </View>
    </Card>
  );
}

export default function SocialScreen() {
  const { session } = useAuth();
  const workspaceId = session?.workspaceId ?? 0;

  const { data: accounts, isLoading } = useQuery({
    queryKey: ['social-accounts', workspaceId],
    queryFn: () => listSocialAccounts(workspaceId),
    enabled: workspaceId > 0,
  });

  if (isLoading) return <Loader />;

  return (
    <SafeAreaView className="flex-1 bg-slate-950">
      <ScrollView className="flex-1" contentContainerStyle={{ padding: 20, gap: 12 }}>
        <Header title="Social Media" subtitle="Verbundene Konten" />

        <View className="mt-4 gap-3">
          {(accounts ?? []).map((account) => (
            <SocialCard key={account.platform} account={account} />
          ))}
        </View>

        <View className="mt-4 rounded-3xl border border-primary/20 bg-primary/5 p-4">
          <Text className="text-sm text-slate-300">
            💡 Verknüpfe deine Social-Media-Konten im Web-Dashboard unter{' '}
            <Text className="text-primary font-medium">Social Accounts</Text>,
            um direkt auf Instagram und TikTok zu posten.
          </Text>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}
```

### 6. `mobile/app/(tabs)/comments.tsx` — Comments mit Sentiment:

```typescript
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { FlatList, Text, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useAuth } from '@/providers/auth-provider';
import { listComments } from '@/features/comments/api';
import { Header } from '@/ui/header';
import { Card } from '@/ui/card';
import { Loader } from '@/ui/loader';
import type { Comment } from '@/types/api';

const SENTIMENT_CONFIG = {
  positive: { label: 'Positiv',  color: '#4ADE80', emoji: '😊' },
  neutral:  { label: 'Neutral',  color: '#FACC15', emoji: '😐' },
  negative: { label: 'Negativ',  color: '#F87171', emoji: '😞' },
};

type Sentiment = keyof typeof SENTIMENT_CONFIG;

function CommentCard({ comment }: { comment: Comment }) {
  const s = SENTIMENT_CONFIG[comment.sentiment];
  return (
    <Card>
      <View className="flex-row items-start gap-3">
        <Text className="text-2xl">{s.emoji}</Text>
        <View className="flex-1">
          <View className="flex-row items-center gap-2 mb-1">
            <Text className="text-sm font-semibold text-white">@{comment.author}</Text>
            <Text className="text-xs capitalize text-slate-500">· {comment.platform}</Text>
          </View>
          <Text className="text-sm text-slate-300 leading-5">{comment.text}</Text>
          {comment.commented_at && (
            <Text className="mt-2 text-xs text-slate-600">
              {new Date(comment.commented_at).toLocaleDateString('de-DE')}
            </Text>
          )}
        </View>
        <View
          className="rounded-full px-2 py-1"
          style={{ backgroundColor: `${s.color}20` }}
        >
          <Text className="text-xs font-semibold" style={{ color: s.color }}>{s.label}</Text>
        </View>
      </View>
    </Card>
  );
}

export default function CommentsScreen() {
  const { session } = useAuth();
  const workspaceId = session?.workspaceId ?? 0;
  const [filter, setFilter] = useState<Sentiment | undefined>();

  const { data: comments, isLoading } = useQuery({
    queryKey: ['comments', workspaceId, filter],
    queryFn: () => listComments(workspaceId, { sentiment: filter }),
    enabled: workspaceId > 0,
  });

  if (isLoading) return <Loader />;

  return (
    <SafeAreaView className="flex-1 bg-slate-950">
      {/* Header + filter */}
      <View className="px-5 pt-5 pb-2">
        <Header title="Kommentare" subtitle={`${comments?.length ?? 0} Einträge`} />
        <View className="mt-4 flex-row gap-2">
          {(['positive', 'neutral', 'negative'] as Sentiment[]).map((s) => (
            <TouchableOpacity
              key={s}
              onPress={() => setFilter(filter === s ? undefined : s)}
              className={`rounded-full border px-3 py-1.5 ${
                filter === s ? 'border-primary bg-primary/20' : 'border-slate-700 bg-slate-900'
              }`}
            >
              <Text className={`text-xs font-semibold ${filter === s ? 'text-primary' : 'text-slate-400'}`}>
                {SENTIMENT_CONFIG[s].emoji} {SENTIMENT_CONFIG[s].label}
              </Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>

      <FlatList
        data={comments ?? []}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <View className="px-5 pb-3">
            <CommentCard comment={item} />
          </View>
        )}
        ListEmptyComponent={
          <View className="items-center px-5 py-16">
            <Text className="text-5xl mb-4">💬</Text>
            <Text className="text-center text-base font-semibold text-white">Keine Kommentare</Text>
            <Text className="mt-2 text-center text-sm text-slate-400">
              Wenn deine Social-Media-Konten verbunden sind, erscheinen hier die Kommentare.
            </Text>
          </View>
        }
        contentContainerStyle={{ paddingBottom: 24 }}
      />
    </SafeAreaView>
  );
}
```

### Son adımlar:

1. Eski template dosyalarını sil:
   - `components/EditScreenInfo.tsx`
   - `components/ExternalLink.tsx`
   - `components/StyledText.tsx`
   - `components/Themed.tsx`
   - `components/useClientOnlyValue.ts`
   - `components/useClientOnlyValue.web.ts`
   - `components/useColorScheme.ts`
   - `components/useColorScheme.web.ts`
   - `constants/Colors.ts`

2. `npm install` çalıştır
3. `npx expo start` ile test et
```

---

## Uygulama Sırası

```
Prompt 1 → Konfigürasyon (package.json, tailwind, babel, metro, .env)
            → npm install çalıştır

Prompt 2 → Data layer (api-client, auth-provider, tüm feature API'ları)
            → TypeScript hatalarını kontrol et: npx tsc --noEmit

Prompt 3 → UI design system (birebir Sichtbar komponentleri)
            → Görsel kontrol: expo start

Prompt 4 → Auth ekranları (login + register)
            → Login/Register akışını test et

Prompt 5 → Tab navigator + feature ekranları
            → Tüm 5 ekranı test et
```

## .env Ayarı (Fiziksel Cihaz için)

```
# Bilgisayarının LAN IP'sini bul (Windows: ipconfig)
EXPO_PUBLIC_API_URL=http://192.168.x.x:1981/klicklocal/backend/public/api/v1
```

## Önemli Notlar

- `mobile/src/` klasöründe `@/` alias'ı `tsconfig.json` paths ile tanımlı
- NativeWind `className` için `global.css` import'u `app/_layout.tsx`'in en üstünde olmalı
- Backend `/comments` endpoint'i Prompt 5 (CLAUDE-CODE-MOBILE-ALIGNMENT.md)'de eklendi — önce o çalışmalı
- Backend `/analytics/kpi` endpoint'i Prompt 6 (CLAUDE-CODE-MOBILE-ALIGNMENT.md)'de eklendi — önce o çalışmalı
