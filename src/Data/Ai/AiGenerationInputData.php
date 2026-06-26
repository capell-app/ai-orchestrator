<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data\Ai;

use Capell\AIOrchestrator\Contracts\AiActionContextInterface;
use Capell\AIOrchestrator\Contracts\AiCreatorContextInterface;
use Capell\AIOrchestrator\Support\Ai\AiResponse;
use Spatie\LaravelData\Data;

class AiGenerationInputData extends Data
{
    /**
     * @param  array<string, mixed>  $options
     * @param  array<int, array{role: string, content: string}>|null  $messages
     * @param  array<string, mixed>|null  $params
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $actionKey,
        public ?AiActionContextInterface $context = null,
        public array $options = [],
        public ?AiCreatorContextInterface $creatorData = null,
        public ?array $messages = null,
        public ?array $params = null,
        public ?AiResponse $response = null,
        public mixed $parsedOutput = null,
        public array $metadata = [],
        public ?int $aiCreatorSessionId = null,
        public ?int $aiCreatorSiteId = null,
        public ?int $aiCreatorUserId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public static function forContextAction(string $actionKey, AiActionContextInterface $context, array $options = []): self
    {
        return new self(
            actionKey: $actionKey,
            context: $context,
            options: $options,
        );
    }

    public static function forAiCreator(string $actionKey, AiCreatorContextInterface $data): self
    {
        return new self(
            actionKey: $actionKey,
            options: ['user_id' => $data->getUserId()],
            creatorData: $data,
            aiCreatorSiteId: $data->getSiteId(),
            aiCreatorUserId: $data->getUserId(),
        );
    }
}
