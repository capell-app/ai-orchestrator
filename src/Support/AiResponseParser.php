<?php

declare(strict_types=1);

namespace Capell\Assistant\Support;

use Capell\Core\Contracts\ParserContract;

class AiResponseParser implements ParserContract
{
    protected const LIST_PATTERNS = [
        '/^[\d\-\*]\.?\s*(.+)$/m',
        '/^\xE2\x80\xA2\s*(.+)$/m',
        '/^\-\s*(.+)$/m',
    ];

    public function parse(string $content): array
    {
        $content = trim($content);
        if ($this->isJson($content)) {
            $json = json_decode($content, true);
            if (is_array($json)) {
                return $this->normalize($json);
            }
        }

        $listed = $this->parseList($content);
        if ($listed !== []) {
            return $listed;
        }

        return [['value' => $content, 'source' => 'fallback']];
    }

    public function normalize(array $data): array
    {
        return array_map(function ($item): array {
            if (is_string($item)) {
                return [
                    'value' => $item,
                    'source' => 'string',
                    'length' => strlen($item),
                ];
            }

            if (is_array($item)) {
                $value = (string) ($item['title'] ?? $item['name'] ?? $item['value'] ?? $item['text'] ?? '');

                return [
                    'value' => $value,
                    'reason' => (string) ($item['reason'] ?? $item['description'] ?? $item['explanation'] ?? ''),
                    'length' => strlen($value),
                    'source' => 'json',
                ] + array_filter($item, static fn ($k): bool => ! in_array($k, ['title', 'name', 'value', 'text', 'reason', 'description', 'explanation'], true), ARRAY_FILTER_USE_KEY);
            }

            return ['value' => (string) $item, 'source' => 'unknown'];
        }, $data);
    }

    protected function isJson(string $content): bool
    {
        $t = trim($content);

        return (str_starts_with($t, '{') && str_ends_with($t, '}')) || (str_starts_with($t, '[') && str_ends_with($t, ']'));
    }

    protected function parseList(string $content): array
    {
        foreach (self::LIST_PATTERNS as $pattern) {
            preg_match_all($pattern, $content, $matches);
            if (isset($matches[1]) && $matches[1] !== []) {
                return array_map(static fn (string $item): array => [
                    'value' => trim($item),
                    'source' => 'list',
                    'length' => strlen($item),
                ], $matches[1]);
            }
        }

        return [];
    }
}
