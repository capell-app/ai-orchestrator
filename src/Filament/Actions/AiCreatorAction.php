<?php

declare(strict_types=1);

namespace Capell\Assistant\Filament\Actions;

use Capell\Assistant\Actions\GenerateAiLayoutAction;
use Capell\Assistant\Actions\SubmitAiCreatorDraftAction;
use Capell\Assistant\DataObjects\AiCreatorData;
use Capell\Assistant\Models\AiCreatorContext;
use Capell\Assistant\Models\AiCreatorSession;
use Capell\Assistant\Policies\AiCreatorPolicy;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AiCreatorAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->name('ai-creator')
            ->label('AI Creator')
            ->icon('heroicon-o-sparkles')
            ->slideOver()
            ->visible(fn (): bool => app(AiCreatorPolicy::class)->isEnabledFor(
                $this->resolveSiteFromRecord()
            ))
            ->form(fn (): array => $this->buildWizardForm())
            ->action(fn (array $data): void => $this->runCreator($data));
    }

    private function buildWizardForm(): array
    {
        return [
            Wizard::make([
                Wizard\Step::make('Describe')
                    ->label('What are we building?')
                    ->schema([
                        Textarea::make('intent')
                            ->label('Describe the page you want to create')
                            ->placeholder('e.g. A homepage for a law firm with a hero, services section, and contact form')
                            ->required()
                            ->rows(4),
                        Select::make('page_count')
                            ->label('How many pages?')
                            ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])
                            ->default(1)
                            ->visible(fn (): bool => $this->isMountedOnSiteResource()),
                    ]),

                Wizard\Step::make('Brand')
                    ->label('Brand & tone')
                    ->schema(fn (): array => $this->buildBrandStep()),

                Wizard\Step::make('Layout')
                    ->label('Proposed layout')
                    ->schema([
                        Repeater::make('layout_preview')
                            ->label('AI-proposed sections (reorder or remove as needed)')
                            ->schema([
                                TextInput::make('section_type')->label('Section type')->disabled(),
                                Textarea::make('fields_preview')->label('Fields preview')->disabled(),
                            ])
                            ->addable(false)
                            ->reorderable()
                            ->columns(2),
                    ]),

                Wizard\Step::make('Review')
                    ->label('Review & submit')
                    ->schema([
                        Textarea::make('review_notes')
                            ->label('Notes for reviewer (optional)')
                            ->rows(3),
                    ]),
            ])->submitAction(
                \Filament\Forms\Components\Actions\Action::make('submit')
                    ->label('Submit for Review')
                    ->color('primary')
            ),
        ];
    }

    private function buildBrandStep(): array
    {
        $siteId = $this->resolveSiteId();
        $existingContext = $siteId ? AiCreatorContext::where('site_id', $siteId)->first() : null;

        return [
            Select::make('tone')
                ->label('Tone of voice')
                ->options([
                    'professional'  => 'Professional & formal',
                    'friendly'      => 'Warm & approachable',
                    'playful'       => 'Fun & playful',
                    'authoritative' => 'Authoritative & expert',
                ])
                ->default($existingContext?->tone ?? 'professional')
                ->required(),

            TextInput::make('industry')
                ->label('Industry / sector')
                ->default($existingContext?->industry ?? '')
                ->placeholder('e.g. Legal, Healthcare, E-commerce'),

            Textarea::make('target_audience')
                ->label('Target audience')
                ->default($existingContext?->target_audience ?? '')
                ->placeholder('e.g. Small business owners aged 30-50')
                ->rows(2),

            Textarea::make('brand_voice_notes')
                ->label('Brand voice notes (optional)')
                ->default($existingContext?->brand_voice_notes ?? '')
                ->placeholder('e.g. We never use jargon. Always end with a call to action.')
                ->rows(2),
        ];
    }

    private function runCreator(array $data): void
    {
        $siteId = $this->resolveSiteId() ?? 0;
        $userId = (int) Auth::id();

        AiCreatorContext::updateOrCreate(
            ['site_id' => $siteId],
            [
                'tone'              => $data['tone'] ?? 'professional',
                'industry'          => $data['industry'] ?? '',
                'target_audience'   => $data['target_audience'] ?? null,
                'brand_voice_notes' => $data['brand_voice_notes'] ?? null,
            ],
        );

        try {
            $creatorData = new AiCreatorData(
                siteId: $siteId,
                userId: $userId,
                intent: $data['intent'],
                pageCount: (int) ($data['page_count'] ?? 1),
                tone: $data['tone'] ?? null,
                industry: $data['industry'] ?? null,
                targetAudience: $data['target_audience'] ?? null,
                brandVoiceNotes: $data['brand_voice_notes'] ?? null,
            );

            app(GenerateAiLayoutAction::class)->handle($creatorData);

            $session = AiCreatorSession::where([
                'site_id' => $siteId,
                'user_id' => $userId,
                'status'  => 'review',
            ])->latest()->first();

            if ($session) {
                app(SubmitAiCreatorDraftAction::class)->handle($session);

                Notification::make()
                    ->title('Layout submitted for review')
                    ->body('Your AI-generated layout has been sent to the workspace for approval.')
                    ->success()
                    ->send();
            }
        } catch (Throwable $e) {
            Notification::make()
                ->title('AI Creator failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function resolveSiteFromRecord(): object
    {
        $record = $this->getRecord();

        if ($record !== null && method_exists($record, 'getSite')) {
            return $record->getSite();
        }

        return (object) ['ai_creator_enabled' => null];
    }

    private function resolveSiteId(): ?int
    {
        $record = $this->getRecord();

        if ($record === null) {
            return null;
        }

        if (method_exists($record, 'getSiteId')) {
            return $record->getSiteId();
        }

        if (isset($record->site_id)) {
            return (int) $record->site_id;
        }

        if (isset($record->id) && str_contains($record::class, 'Site')) {
            return (int) $record->id;
        }

        return null;
    }

    private function isMountedOnSiteResource(): bool
    {
        $record = $this->getRecord();

        return $record !== null && str_contains($record::class, 'Site');
    }
}
