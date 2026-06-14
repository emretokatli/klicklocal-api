<?php

namespace App\Enums;

/**
 * Content categories used by the weekly content plan. The string value is the
 * stable slug passed to the /ai flow as a query param; label() is the German UI
 * text.
 */
enum ContentCategory: string
{
    case Angebot = 'angebot';
    case BehindTheScenes = 'behind_the_scenes';
    case Trend = 'trend';
    case Lokal = 'lokal';

    public function label(): string
    {
        return match ($this) {
            self::Angebot => 'Angebot',
            self::BehindTheScenes => 'Behind-the-Scenes',
            self::Trend => 'Trend',
            self::Lokal => 'Lokal',
        };
    }
}
