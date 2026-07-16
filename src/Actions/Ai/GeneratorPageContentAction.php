<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions\Ai;

use Capell\AIOrchestrator\Contracts\AiActionContextInterface;
use Capell\AIOrchestrator\Data\Ai\AiGenerationInputData;
use Capell\AIOrchestrator\Events\Ai\AiGenerationCompleted;
use Capell\AIOrchestrator\Events\Ai\AiGenerationFailed;
use Capell\AIOrchestrator\Events\Ai\AiGenerationStarted;
use Capell\AIOrchestrator\Support\Ai\Pipelines\GenerateContentPipeline;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

class GeneratorPageContentAction
{
    use AsFake;
    use AsObject;

    public function __construct(private readonly GenerateContentPipeline $pipeline) {}

    /**
     * @param  array{user_id?:int|null,site_id?:int|null,current_title?:string|null,target_length?:int|null,refactor?:bool|null}  $options
     */
    public function handle(AiActionContextInterface $context, array $options = []): string
    {
        $startTime = microtime(true);
        Event::dispatch(new AiGenerationStarted(static::class, [$context, $options]));

        try {
            $input = AiGenerationInputData::forContextAction('GeneratorPageContentAction', $context, $options);
            $result = $this->pipeline->execute($input);

            $duration = microtime(true) - $startTime;

            Event::dispatch(new AiGenerationCompleted(static::class, $result->output, []));

            return is_scalar($result->output) ? (string) $result->output : '';
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
