# Worked extension examples

These developer-facing recipes are kept beside the package contract. Replace the example values with the site-specific records and data objects used by the calling workflow.

<!-- example: contract Capell\AIOrchestrator\Contracts\AIOrchestratorModule -->

```php
<?php
declare(strict_types=1);
final class ExampleAIOrchestratorModuleImplementation implements \Capell\AIOrchestrator\Contracts\AIOrchestratorModule
{
    public function key(): string
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
    public function label(): string
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
    /**
     * @return array<int, AIOrchestratorCapabilityData>
     */
    public function capabilities(): array
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
}

app()->bind(\Capell\AIOrchestrator\Contracts\AIOrchestratorModule::class, ExampleAIOrchestratorModuleImplementation::class);
```

<!-- example: contract Capell\AIOrchestrator\Contracts\AIOrchestratorPolicyGuardrail -->

```php
<?php
declare(strict_types=1);
final class ExampleAIOrchestratorPolicyGuardrailImplementation implements \Capell\AIOrchestrator\Contracts\AIOrchestratorPolicyGuardrail
{
    public function allows(\Capell\AIOrchestrator\Data\AIOrchestratorRunData $run, \Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData $capability): bool
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
    public function denialMessage(\Capell\AIOrchestrator\Data\AIOrchestratorRunData $run, \Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData $capability): string
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
}

app()->bind(\Capell\AIOrchestrator\Contracts\AIOrchestratorPolicyGuardrail::class, ExampleAIOrchestratorPolicyGuardrailImplementation::class);
```

<!-- example: contract Capell\AIOrchestrator\Contracts\AiActionContextInterface -->

```php
<?php
declare(strict_types=1);
final class ExampleAiActionContextInterfaceImplementation implements \Capell\AIOrchestrator\Contracts\AiActionContextInterface
{
    public function getContent(): string
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
    public function getKeywords(): string
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
    public function getPageId(): int|string
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
    public function getPageType(): string
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
    public function getLanguageId(): int
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
}

app()->bind(\Capell\AIOrchestrator\Contracts\AiActionContextInterface::class, ExampleAiActionContextInterfaceImplementation::class);
```

<!-- example: contract Capell\AIOrchestrator\Contracts\AiCreatorContextInterface -->

```php
<?php
declare(strict_types=1);
final class ExampleAiCreatorContextInterfaceImplementation implements \Capell\AIOrchestrator\Contracts\AiCreatorContextInterface
{
    public function getSiteId(): int
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
    public function getUserId(): int
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
}

app()->bind(\Capell\AIOrchestrator\Contracts\AiCreatorContextInterface::class, ExampleAiCreatorContextInterfaceImplementation::class);
```

<!-- example: contract Capell\AIOrchestrator\Contracts\ProviderOutputValidator -->

```php
<?php
declare(strict_types=1);
final class ExampleProviderOutputValidatorImplementation implements \Capell\AIOrchestrator\Contracts\ProviderOutputValidator
{
    public function policyIdentity(): string
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
    public function policyDigest(): string
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
    public function validate(string $outputIdentity, string $providerOutput): ?\Capell\AIOrchestrator\Data\Ai\ValidatedProviderOutputData
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
}

app()->bind(\Capell\AIOrchestrator\Contracts\ProviderOutputValidator::class, ExampleProviderOutputValidatorImplementation::class);
```

<!-- example: contract Capell\AIOrchestrator\Contracts\VersionedContract -->

```php
<?php
declare(strict_types=1);
final class ExampleVersionedContractImplementation implements \Capell\AIOrchestrator\Contracts\VersionedContract
{
    public function schemaVersion(): int
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
    /** @return array<string, mixed> */
    public function toCanonicalArray(): array
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
    public function canonicalJson(): string
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
    public function digest(): string
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
}

app()->bind(\Capell\AIOrchestrator\Contracts\VersionedContract::class, ExampleVersionedContractImplementation::class);
```
