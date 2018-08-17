<?php

declare(strict_types=1);

use Capell\Admin\Contracts\Extenders\ResourceHeaderActionExtender;
use Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider;
use Capell\AIOrchestrator\Support\Admin\AiAssistantPageResourceExtender;
use Capell\Core\Facades\CapellCore;

it('does not tag admin extenders when the package is not installed', function (): void {
    $tagsProperty = new ReflectionProperty(app(), 'tags');
    $originalTags = $tagsProperty->getValue(app());
    throw_unless(is_array($originalTags), RuntimeException::class, 'Expected the application container tags to be an array.');

    $tags = $originalTags;
    $tags[ResourceHeaderActionExtender::TAG] = array_values(array_filter(
        (array) ($tags[ResourceHeaderActionExtender::TAG] ?? []),
        static fn (mixed $abstract): bool => $abstract !== AiAssistantPageResourceExtender::class,
    ));

    $tagsProperty->setValue(app(), $tags);
    CapellCore::forcePackageInstalled(AIOrchestratorServiceProvider::$packageName, false);

    try {
        $provider = app()->getProvider(AIOrchestratorServiceProvider::class);
        throw_unless($provider instanceof AIOrchestratorServiceProvider, RuntimeException::class, 'Expected the AI Orchestrator service provider to be loaded.');

        $provider->registeringPackage();

        $hasAiExtender = false;

        foreach (app()->tagged(ResourceHeaderActionExtender::TAG) as $extender) {
            if ($extender instanceof AiAssistantPageResourceExtender) {
                $hasAiExtender = true;
                break;
            }
        }

        expect($hasAiExtender)->toBeFalse();
    } finally {
        $tagsProperty->setValue(app(), $originalTags);
        CapellCore::forcePackageInstalled(AIOrchestratorServiceProvider::$packageName);
    }
});
