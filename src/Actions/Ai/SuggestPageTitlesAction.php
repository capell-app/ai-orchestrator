<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions\Ai;

use Capell\AIOrchestrator\Contracts\AiActionContextInterface;
use Capell\AIOrchestrator\Data\Ai\AiGenerationInputData;
use Capell\AIOrchestrator\Events\Ai\AiGenerationCompleted;
use Capell\AIOrchestrator\Events\Ai\AiGenerationFailed;
use Capell\AIOrchestrator\Events\Ai\AiGenerationStarted;
use Capell\AIOrchestrator\Support\Ai\Pipelines\SuggestTitlesPipeline;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class SuggestPageTitlesAction
{
    use AsAction;

    public function __construct(private readonly SuggestTitlesPipeline $pipeline) {}

    /**
     * @param  array<array-key, mixed>  $options
     * @return array<int, string>
     */
    public function handle(AiActionContextInterface $context, array $options = []): array
    {
        $startTime = microtime(true);
        Event::dispatch(new AiGenerationStarted(static::class, [$context, $options]));

        try {
            $input = AiGenerationInputData::forContextAction('SuggestPageTitlesAction', $context, $options);
            $result = $this->pipeline->execute($input);

            $duration = microtime(true) - $startTime;
            Log::info('AI Action completed', [
                'action' => static::class,
                'duration_ms' => round($duration * 1000, 2),
            ]);
            Event::dispatch(new AiGenerationCompleted(static::class, $result->output, []));

            return (array) $result->output;
        } catch (Throwable $throwable) {
            Log::error('AI Action failed', [
                'action' => static::class,
                'error' => $throwable->getMessage(),
            ]);
            Event::dispatch(new AiGenerationFailed(static::class, $throwable));
            throw $throwable;
        }
    }
}
