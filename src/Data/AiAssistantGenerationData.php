<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\LaravelData\Data;

/**
 * Pure inputs for a single AI Assistant generation pass, gathered by the
 * Filament extender from the active translation's form state and the edited
 * record, then handed to GenerateAiAssistantFieldsAction.
 */
class AiAssistantGenerationData extends Data
{
    /**
     * @param  array<int, string>  $fields  Short capability keys to generate (title|content|meta).
     */
    public function __construct(
        public array $fields = [],
        public string $content = '',
        public string $currentTitle = '',
        public string $keywords = '',
        public int|string|null $pageId = null,
        public ?string $pageType = null,
        public int $languageId = 0,
        public bool $titleIncludeCurrent = false,
        public bool $contentRefactor = false,
        public ?int $contentTargetLength = null,
        public bool $metaIncludeCurrent = false,
        public ?Authenticatable $actor = null,
    ) {}
}
