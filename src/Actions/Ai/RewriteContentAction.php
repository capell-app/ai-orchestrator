<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions\Ai;

use Capell\AIOrchestrator\Contracts\AiActionContextInterface;
use Capell\AIOrchestrator\Data\Ai\AiGenerationInputData;
use Capell\AIOrchestrator\Enums\AiRewriteMode;
use Capell\AIOrchestrator\Support\Ai\Pipelines\GenerateContentPipeline;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static string run(AiActionContextInterface $context, AiRewriteMode $mode, string $instruction = '', array<string, mixed> $options = []) */
class RewriteContentAction
{
    use AsFake;
    use AsObject;

    public function __construct(private readonly GenerateContentPipeline $pipeline) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function handle(AiActionContextInterface $context, AiRewriteMode $mode, string $instruction = '', array $options = []): string
    {
        $input = AiGenerationInputData::forContextAction('RewriteContentAction', $context, [
            ...$options,
            'prompt_key' => 'rewrite_content',
            'mode' => $mode->instruction(),
            'instruction' => $instruction,
            'refactor' => true,
        ]);

        $output = $this->pipeline->execute($input)->output;

        return is_scalar($output) ? (string) $output : '';
    }
}
