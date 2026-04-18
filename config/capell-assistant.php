<?php

declare(strict_types=1);

use Capell\Assistant\Actions\GeneratorPageContentAction;

return [
    'prism' => [
        'provider' => env('AI_PROVIDER', 'openai'),
        'model' => env('AI_MODEL', 'gpt-4o'),
        'max_retries' => 3,
        'retry_delay_ms' => 500,
        'max_tokens' => 4096,
        'image_provider' => env('AI_IMAGE_PROVIDER', 'openai'),
        'image_model' => env('AI_IMAGE_MODEL', 'dall-e-3'),
        'image_size' => env('AI_IMAGE_SIZE', '1024x1024'),
    ],
    'ai_creator' => [
        'enabled' => env('AI_CREATOR_ENABLED', true),
    ],
    'prompts' => [
        'title_generation' => [
            'system' => 'You are a helpful assistant that writes concise, SEO-friendly page titles.',
            'user_template' => 'Generate a compelling page title for the following content: {{content}}. Current title: {{current_title}}. Keywords: {{keywords}}. Limit to 70 characters.',
        ],
        'meta_description' => [
            'system' => 'You are a helpful assistant that writes accurate and engaging meta descriptions.',
            'user_template' => 'Write an SEO meta description (max 160 characters) for: {{content}}. Keywords: {{keywords}}.',
        ],
        'content_generation' => [
            'system' => 'You are a helpful assistant that writes engaging, accessible, and SEO-friendly HTML page content. Prefer short paragraphs, meaningful headings (h2/h3), and occasional lists. Keep tone friendly and informative.',
            'user_template' => 'Generate or refactor content. Title: {{current_title}}. Keywords: {{keywords}}. Existing content: {{content}}. Target length: {{target_length}} words. Refactor existing: {{refactor}}. Output clean HTML only (paragraphs, h2/h3 headings, lists). Include a concise call to action where appropriate. Avoid scripts, styles, iframes, and external links.',
        ],
    ],
    'rate_limiting' => [
        'enabled' => true,
        'requests_per_minute' => 60,
    ],
    'features' => [
        'title_generation' => [
            'enabled' => true,
            'model' => 'gpt-4o',
            'handler' => 'Capell\\Admin\\Actions\\AI\\GeneratePageTitleAction',
        ],
        'meta_description' => [
            'enabled' => true,
            'model' => 'gpt-4o',
            'handler' => 'Capell\\Admin\\Actions\\AI\\GenerateMetaDescriptionAction',
        ],
        'content_generation' => [
            'enabled' => true,
            'model' => 'gpt-4o',
            'handler' => GeneratorPageContentAction::class,
        ],
        'ai_creator' => [
            'enabled' => true,
            'model' => 'gpt-4o',
            'handler' => null,
        ],
    ],
    'cache' => [
        'ttl' => 86400, // 1 day
    ],
];
