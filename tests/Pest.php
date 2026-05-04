<?php

declare(strict_types=1);

use Capell\Assistant\Tests\AssistantTestCase;

pest()->extend(AssistantTestCase::class)->group('assistant')->in(__DIR__);
