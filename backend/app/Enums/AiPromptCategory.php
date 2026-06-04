<?php

namespace App\Enums;

enum AiPromptCategory: string
{
    case Caption = 'caption';
    case Content = 'content';
    case Hashtag = 'hashtag';
    case Reply = 'reply';
    case Scheduling = 'scheduling';
    case BrandVoice = 'brand_voice';
}
