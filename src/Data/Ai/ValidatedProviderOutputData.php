<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data\Ai;

use Capell\AIOrchestrator\Support\Contracts\CanonicalContract;
use InvalidArgumentException;

final readonly class ValidatedProviderOutputData extends CanonicalContract
{
    public function __construct(public string $identity, public string $digest, public string $content)
    {
        self::nonEmpty($identity, 'identity');
        self::nonEmpty($content, 'content');
        if (preg_match('/^[a-f0-9]{64}$/', $digest) !== 1 || ! hash_equals(hash('sha256', $content), $digest)) {
            throw new InvalidArgumentException('Validated output digest must match its content.');
        }
    }

    public static function fromValidatedContent(string $identity, string $content): self
    {
        return new self($identity, hash('sha256', $content), $content);
    }

    /** @param array<string, mixed> $payload */
    public static function fromCanonicalArray(array $payload): self
    {
        self::assertVersion($payload);

        return new self(self::stringValue($payload, 'identity'), self::stringValue($payload, 'digest'), self::stringValue($payload, 'content'));
    }

    /** @return array<string, mixed> */
    public function toCanonicalArray(): array
    {
        return ['content' => $this->content, 'digest' => $this->digest, 'identity' => $this->identity, 'schema_version' => $this->schemaVersion()];
    }
}
