<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai;

class AiResponseParser
{
    /**
     * @var array<array-key, mixed>
     */
    protected readonly array $listPatterns;

    public function __construct()
    {
        $this->listPatterns = [
            '/^[\d\-\*]\.?\s*(.+)$/m',
            '/^\xE2\x80\xA2\s*(.+)$/m',
            '/^\-\s*(.+)$/m',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
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

    /**
     * @param  array<int|string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public function normalize(array $data): array
    {
        return array_values(array_map(function (mixed $item): array {
            if (is_string($item)) {
                return [
                    'value' => $item,
                    'source' => 'string',
                    'length' => strlen($item),
                ];
            }

            if (is_array($item)) {
                $value = (string) ($item['title'] ?? $item['name'] ?? $item['value'] ?? $item['text'] ?? '');
                $keywords = ['title', 'name', 'value', 'text', 'reason', 'description', 'explanation'];

                return [
                    'value' => $value,
                    'reason' => (string) ($item['reason'] ?? $item['description'] ?? $item['explanation'] ?? ''),
                    'length' => strlen($value),
                    'source' => 'json',
                ] + array_filter(
                    $item,
                    static fn (string $keyword): bool => ! in_array($keyword, $keywords, true),
                    ARRAY_FILTER_USE_KEY,
                );
            }

            return ['value' => (string) $item, 'source' => 'unknown'];
        }, $data));
    }

    protected function isJson(string $content): bool
    {
        $t = trim($content);

        return (str_starts_with($t, '{') && str_ends_with($t, '}')) || (str_starts_with($t, '[') && str_ends_with($t, ']'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseList(string $content): array
    {
        foreach ($this->listPatterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            if ($matches[1] !== []) {
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
