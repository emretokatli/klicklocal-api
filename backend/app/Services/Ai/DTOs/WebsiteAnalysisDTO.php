<?php

namespace App\Services\Ai\DTOs;

readonly class WebsiteAnalysisDTO
{
    public function __construct(
        public string $description,
        public string $targetAudience,
        public string $uniqueValueProposition,
        public string $additionalNotes,
        public ?string $city,
        public string $model,
        public int $tokensUsed,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'target_audience' => $this->targetAudience,
            'unique_value_proposition' => $this->uniqueValueProposition,
            'additional_notes' => $this->additionalNotes,
            'city' => $this->city,
        ];
    }
}
