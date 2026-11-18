<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Enums;

enum AiProviderProblem: string
{
    case MissingKey = 'missing_key';
    case UnknownModel = 'unknown_model';
    case ProviderMismatch = 'provider_mismatch';

    public function message(): string
    {
        return __('capell-ai-orchestrator::package.provider_problem_' . $this->value);
    }
}
