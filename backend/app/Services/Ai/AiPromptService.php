<?php

namespace App\Services\Ai;

use App\Enums\AiPromptCategory;
use App\Models\AiPromptTemplate;
use App\Models\User;
use Illuminate\Support\Collection;

class AiPromptService
{
    /**
     * @return Collection<int, AiPromptTemplate>
     */
    public function list(?AiPromptCategory $category = null, ?bool $activeOnly = null): Collection
    {
        return AiPromptTemplate::query()
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($activeOnly === true, fn ($q) => $q->where('is_active', true))
            ->orderBy('category')
            ->orderBy('name')
            ->get();
    }

    public function findByKey(string $key): ?AiPromptTemplate
    {
        return AiPromptTemplate::query()->where('key', $key)->first();
    }

    public function activeTemplate(string $key): ?AiPromptTemplate
    {
        return AiPromptTemplate::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $editor = null): AiPromptTemplate
    {
        $data['updated_by'] = $editor?->id;

        return AiPromptTemplate::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AiPromptTemplate $template, array $data, ?User $editor = null): AiPromptTemplate
    {
        $data['updated_by'] = $editor?->id;
        $data['version'] = $template->version + 1;
        $template->update($data);

        return $template->fresh();
    }

    public function setActive(AiPromptTemplate $template, bool $active): AiPromptTemplate
    {
        $template->update(['is_active' => $active]);

        return $template->fresh();
    }
}
