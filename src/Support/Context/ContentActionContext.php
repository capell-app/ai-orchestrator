<?php

declare(strict_types=1);

namespace Capell\Assistant\Support\Context;

use Capell\Assistant\Contracts\AiActionContextInterface;

final readonly class ContentActionContext implements AiActionContextInterface
{
    public function __construct(
        private string $content,
        private string $keywords = '',
        private int $pageId = 0,
        private int $languageId = 0,
    ) {}

    public function getContent(): string
    {
        return $this->content;
    }

    public function getKeywords(): string
    {
        return $this->keywords;
    }

    public function getPageId(): int
    {
        return $this->pageId;
    }

    public function getLanguageId(): int
    {
        return $this->languageId;
    }
}
