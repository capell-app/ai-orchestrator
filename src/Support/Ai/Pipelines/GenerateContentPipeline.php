<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai\Pipelines;

use Capell\AIOrchestrator\Actions\Ai\RecordAiGenerationAction;
use Capell\AIOrchestrator\Contracts\AiActionContextInterface;
use Capell\AIOrchestrator\Data\Ai\AiGenerationInputData;
use Capell\AIOrchestrator\Data\Ai\AiGenerationResultData;
use Capell\AIOrchestrator\Support\Ai\AiRateLimiter;
use Capell\AIOrchestrator\Support\Ai\AiResponse;
use Capell\AIOrchestrator\Support\Ai\Concerns\NormalizesAiValues;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Capell\AIOrchestrator\Support\Ai\PromptRepository;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Pipeline\Pipeline;
use InvalidArgumentException;
use RuntimeException;

class GenerateContentPipeline
{
    use NormalizesAiValues;

    public function __construct(
        private readonly PromptRepository $prompts,
        private readonly PrismProvider $provider,
        private readonly AiRateLimiter $rateLimiter,
    ) {}

    public function execute(AiGenerationInputData $input): AiGenerationResultData
    {
        $initialPayload = [
            'input' => $input,
            'context' => $input->context,
            'options' => $input->options,
        ];

        $payload = resolve(Pipeline::class)
            ->send($initialPayload)
            ->through([
                fn (array $payload, callable $next): array => $this->validateInput($payload, $next),
                fn (array $payload, callable $next): array => $this->checkRateLimit($payload, $next),
                fn (array $payload, callable $next): array => $this->executeAiCall($payload, $next),
                fn (array $payload, callable $next): array => $this->parseResponse($payload, $next),
                fn (array $payload, callable $next): array => $this->recordGeneration($payload, $next),
            ])
            ->thenReturn();

        $resultData = is_array($payload) ? ($payload['result_data'] ?? null) : null;
        throw_unless($resultData instanceof AiGenerationResultData, RuntimeException::class, 'Content generation pipeline produced no result data');

        return $resultData;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function validateInput(array $payload, callable $next): array
    {
        $context = $payload['context'] ?? null;
        throw_unless($context instanceof AiActionContextInterface, InvalidArgumentException::class, 'Missing AiActionContextInterface context');

        return $next($payload);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function checkRateLimit(array $payload, callable $next): array
    {
        $options = $this->aiArray($payload['options'] ?? null);
        $identifier = $this->aiString($options['user_id'] ?? 'global') ?: 'global';
        $this->rateLimiter->checkLimit($identifier, 'content_generation');

        return $next($payload);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function executeAiCall(array $payload, callable $next): array
    {
        /** @var AiActionContextInterface $context */
        $context = $payload['context'];
        $options = $this->aiArray($payload['options'] ?? null);
        $prompt = $this->aiArray($this->prompts->get('content_generation'));

        $userMessage = strtr($this->aiString($prompt['user_template'] ?? ''), [
            '{{current_title}}' => $this->aiString($options['current_title'] ?? ''),
            '{{keywords}}' => $context->getKeywords() ?? '',
            '{{content}}' => $context->getContent() ?? '',
            '{{target_length}}' => ($options['target_length'] ?? null) !== null ? $this->aiString($options['target_length']) : 'auto',
            '{{refactor}}' => ($options['refactor'] ?? true) ? 'yes' : 'no',
        ]);

        $messages = [
            ['role' => 'system', 'content' => $this->aiString($prompt['system'] ?? '')],
            ['role' => 'user', 'content' => $userMessage],
        ];

        $params = [
            'model' => $this->aiString($prompt['model'] ?? config('capell-ai-orchestrator.prism.model')),
            'messages' => $messages,
            'max_tokens' => config('capell-ai-orchestrator.prism.max_tokens', 4096),
            'temperature' => 0.7,
            'site_id' => $this->positiveIntOrNull($options['site_id'] ?? null),
        ];

        $response = $this->provider->chat($params);
        $payload['ai_response'] = $response;
        $payload['ai_messages'] = $messages;
        $payload['ai_params'] = $params;

        return $next($payload);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function parseResponse(array $payload, callable $next): array
    {
        /** @var AiResponse $response */
        $response = $payload['ai_response'];
        $raw = trim($response->content);

        // Sanitize final HTML/text string
        $payload['result'] = $this->sanitizeHtml($raw);

        return $next($payload);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function recordGeneration(array $payload, callable $next): array
    {
        /** @var AiGenerationInputData $input */
        $input = $payload['input'];
        /** @var AiResponse $response */
        $response = $payload['ai_response'];
        /** @var AiActionContextInterface $context */
        $context = $payload['context'];
        $result = $this->aiString($payload['result'] ?? '');

        $resultData = AiGenerationResultData::make(
            actionKey: $input->actionKey,
            output: $result,
            inputText: $context->getContent(),
            outputText: $result,
            response: $response,
            messages: $this->aiMessages($payload['ai_messages'] ?? null),
            params: $this->aiParams($payload['ai_params'] ?? null),
            pageableId: $context->getPageId(),
            pageableType: $context->getPageType(),
            languageId: $context->getLanguageId(),
            siteId: $this->positiveIntOrNull($input->options['site_id'] ?? null),
        );

        $resultData->history = RecordAiGenerationAction::run($resultData);
        $payload['result_data'] = $resultData;

        return $next($payload);
    }

    /**
     * Basic sanitizer to keep AI output safe and user-friendly.
     * - strips script/style blocks
     * - removes inline event handlers (on*)
     * - neutralizes javascript: URLs
     * - removes external absolute links, preserves relative links
     */
    private function sanitizeHtml(string $html): string
    {
        if (class_exists(DOMDocument::class)) {
            return $this->sanitizeHtmlWithDom($html);
        }

        return $this->sanitizeHtmlWithPatterns($html);
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function sanitizeHtmlWithDom(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorHandling = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="capell-sanitizer-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorHandling);

        $root = $document->getElementById('capell-sanitizer-root');

        if (! $root instanceof DOMElement) {
            return $this->sanitizeHtmlWithPatterns($html);
        }

        $this->sanitizeChildNodes($root, $document);

        $output = '';
        foreach ($root->childNodes as $childNode) {
            $output .= $document->saveHTML($childNode);
        }

        return $output;
    }

    private function sanitizeChildNodes(DOMNode $node, DOMDocument $document): void
    {
        $childNode = $node->firstChild;

        while ($childNode instanceof DOMNode) {
            $nextNode = $childNode->nextSibling;

            if ($childNode instanceof DOMElement) {
                $tagName = strtolower($childNode->tagName);

                if (in_array($tagName, $this->blockedTags(), true)) {
                    $node->removeChild($childNode);
                    $childNode = $nextNode;

                    continue;
                }

                if (! in_array($tagName, $this->allowedTags(), true)) {
                    $this->sanitizeChildNodes($childNode, $document);
                    $this->unwrapElement($childNode);
                    $childNode = $nextNode;

                    continue;
                }

                if ($tagName === 'a' && ! $this->isSafeRelativeHref($childNode->getAttribute('href'))) {
                    $node->replaceChild($this->textSpan($document, $childNode->textContent), $childNode);
                    $childNode = $nextNode;

                    continue;
                }

                $this->sanitizeAttributes($childNode);
                $this->sanitizeChildNodes($childNode, $document);
            }

            $childNode = $nextNode;
        }
    }

    private function sanitizeAttributes(DOMElement $element): void
    {
        $tagName = strtolower($element->tagName);
        $allowedAttributes = $this->allowedAttributesForTag($tagName);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $attributeName = strtolower($attribute->nodeName);
            $attributeValue = $attribute->nodeValue ?? '';

            if (str_starts_with($attributeName, 'on') || ! in_array($attributeName, $allowedAttributes, true)) {
                $element->removeAttributeNode($attribute);

                continue;
            }

            if ($attributeName === 'src' && ! $this->isSafeImageSource($attributeValue)) {
                $element->removeAttributeNode($attribute);
            }
        }
    }

    private function unwrapElement(DOMElement $element): void
    {
        $parentNode = $element->parentNode;

        if (! $parentNode instanceof DOMNode) {
            return;
        }

        while ($element->firstChild instanceof DOMNode) {
            $parentNode->insertBefore($element->firstChild, $element);
        }

        $parentNode->removeChild($element);
    }

    private function textSpan(DOMDocument $document, string $text): DOMElement
    {
        $span = $document->createElement('span');
        $span->appendChild($document->createTextNode($text));

        return $span;
    }

    /** @return list<string> */
    private function blockedTags(): array
    {
        return ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math'];
    }

    /** @return list<string> */
    private function allowedTags(): array
    {
        return [
            'a',
            'b',
            'blockquote',
            'br',
            'code',
            'div',
            'em',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'i',
            'img',
            'li',
            'ol',
            'p',
            'pre',
            'span',
            'strong',
            'u',
            'ul',
        ];
    }

    /** @return list<string> */
    private function allowedAttributesForTag(string $tagName): array
    {
        return match ($tagName) {
            'a' => ['href', 'title'],
            'img' => ['src', 'alt', 'title', 'width', 'height'],
            default => [],
        };
    }

    private function isSafeRelativeHref(string $href): bool
    {
        $normalizedHref = $this->normalizeUrl($href);

        return str_starts_with($normalizedHref, '/')
            || str_starts_with($normalizedHref, './')
            || str_starts_with($normalizedHref, '../')
            || str_starts_with($normalizedHref, '#');
    }

    private function isSafeImageSource(string $source): bool
    {
        $normalizedSource = $this->normalizeUrl($source);

        if ($normalizedSource === '') {
            return false;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $normalizedSource) !== 1) {
            return ! str_starts_with($normalizedSource, '//');
        }

        return preg_match('#^https?://#i', $normalizedSource) === 1;
    }

    private function normalizeUrl(string $value): string
    {
        $decoded = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return (string) preg_replace('/[\x00-\x20]+/', '', $decoded);
    }

    private function sanitizeHtmlWithPatterns(string $html): string
    {
        // Remove <script> and <style> blocks
        $clean = preg_replace('#<\s*(script|style|iframe|object|embed|svg|math)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html) ?? $html;

        // Remove <iframe> blocks entirely
        $clean = preg_replace('#<\s*iframe[^>]*>.*?<\s*/\s*iframe\s*>#is', '', $clean) ?? $clean;

        // Remove inline event handlers like onclick, onerror, onload
        $clean = preg_replace('/\son[a-z0-9:_-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;

        // Neutralize javascript: URLs
        $clean = preg_replace('/\shref\s*=\s*(?:"\s*javascript:[^"]*"|\'\s*javascript:[^\']*\'|\s*javascript:[^\s>]+)/i', ' href="#"', $clean) ?? $clean;

        // Convert external absolute links to plain text or '#'
        // Preserve relative links (/, ./, ../) and anchors (#...)
        $clean = preg_replace_callback('#<a\s+[^>]*href\s*=\s*(["\'])([^"\']+)\1[^>]*>(.*?)</a>#is', function (array $matches): string {
            $href = $matches[2];
            $text = trim(strip_tags($matches[3]));

            $isRelative = str_starts_with($href, '/') || str_starts_with($href, './') || str_starts_with($href, '../') || str_starts_with($href, '#');
            $isAbsolute = preg_match('#^https?://#i', $href) === 1;

            if ($isRelative) {
                // keep relative links, but remove target and rel attributes
                $safeAnchor = preg_replace(['/\s+target=(["\']).*?\1/i', '/\s+rel=(["\']).*?\1/i'], ['', ''], $matches[0]) ?? $matches[0];

                return $safeAnchor;
            }

            if ($isAbsolute) {
                // Replace external links with a non-clickable span containing text
                return $text !== '' ? '<span>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>' : '<span></span>';
            }

            // Unknown scheme: neutralize
            return '<span>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
        }, $clean) ?? $clean;

        return $clean;
    }
}
