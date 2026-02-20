<?php

declare(strict_types=1);

namespace Capell\Assistant\Contracts;

interface ActionContract
{
    public function handle(...$args): mixed;

    public function validate(array $input): bool;
}
