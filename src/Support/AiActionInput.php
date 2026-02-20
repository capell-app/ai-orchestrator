<?php

declare(strict_types=1);

namespace Capell\Assistant\Support;

use Capell\Core\Models\PageTranslation;

final class AiActionInput
{
    public function __construct(
        public PageTranslation $translation,
        /** @var array<string, mixed> */
        public array $options = [],
    ) {}
}
