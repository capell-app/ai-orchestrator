<?php

declare(strict_types=1);

namespace Capell\Assistant\Handlers;

use Capell\Admin\Support\AdminEventHandlerInterface;
use Capell\Assistant\Support\OpenAIProvider;
use Filament\Notifications\Notification;
use Livewire\Component;

final class ClearCircuitBreakerHandler implements AdminEventHandlerInterface
{
    /** @param array<int, mixed> $payload */
    public function handle(array $payload, Component $component): void
    {
        resolve(OpenAIProvider::class)->resetCircuitBreaker();

        Notification::make('circuit-breaker-cleared')
            ->title(__('capell-assistant::message.circuit_breaker_cleared'))
            ->success()
            ->send();
    }
}
