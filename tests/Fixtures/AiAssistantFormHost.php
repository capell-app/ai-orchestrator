<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Tests\Fixtures;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

final class AiAssistantFormHost extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, mixed> */
    public array $assistantData = [];

    public ?int $activeTab = null;

    public ?Model $record = null;

    public function getRecord(): ?Model
    {
        return $this->record;
    }
}
