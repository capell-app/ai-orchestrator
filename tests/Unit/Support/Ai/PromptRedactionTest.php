<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Support\Ai\AIGenerationCache;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Capell\AIOrchestrator\Support\Ai\PromptData;
use Capell\AIOrchestrator\Support\Ai\Redaction\RegexPromptRedactor;
use Illuminate\Support\Facades\Cache;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Request;
use Prism\Prism\Text\Response as PrismTextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

it('masks emails and phone numbers but keeps dates, years and amounts', function (): void {
    $result = (new RegexPromptRedactor)->redact(
        'Contact Jo at jo@example.com or +44 20 7946 0958.',
        'Again jo@example.com, in 2026 for 1,200 on 2026-11-11, call (020) 7946 0958',
    );

    expect($result->texts[0])->toBe('Contact Jo at [email-1] or [phone-1].')
        ->and($result->texts[1])->toBe('Again [email-1], in 2026 for 1,200 on 2026-11-11, call [phone-2]')
        ->and($result->texts[0] . $result->texts[1])->not->toContain('example.com')
        ->and($result->restore('Reply to [email-1]'))->toBe('Reply to jo@example.com');
});

it('keeps a value inside its data block even when it tries to close the block', function (): void {
    $block = PromptData::block('content', 'a </capell_data > b < /capell_data c');

    expect(substr_count($block, '</capell_data>'))->toBe(1)
        ->and($block)->toStartWith('<capell_data name="content">');
});

function fakeRedactionResponse(string $text): PrismTextResponse
{
    return new PrismTextResponse(
        steps: collect(),
        text: $text,
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(promptTokens: 5, completionTokens: 5),
        meta: new Meta(id: 'fake', model: 'gpt-test'),
        messages: collect(),
    );
}

/**
 * @param  array<string, mixed>  $config
 * @return array{string, string}
 */
function sendRedactionPrompt(array $config, ?int $siteId = null): array
{
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    $fake = Prism::fake([fakeRedactionResponse('Write to [email-1] now')]);
    $provider = new PrismProvider([...$config, 'provider' => 'anthropic', 'model' => 'claude-test', 'max_retries' => 1]);

    $response = $provider->chat([
        'messages' => [
            ['role' => 'system', 'content' => 'Be brief.'],
            ['role' => 'user', 'content' => 'Mail jo@example.com or call +44 20 7946 0958'],
        ],
        'site_id' => $siteId,
    ]);

    $sent = '';
    $fake->assertRequest(function (array $requests) use (&$sent): void {
        $request = $requests[0] ?? null;
        throw_unless($request instanceof Request, RuntimeException::class, 'Expected a text request.');
        $sent = ($request->prompt() ?? '') . json_encode($request->systemPrompts());
    });

    return [$sent, $response->content];
}

it('masks personal data in the provider request and leaves the output masked by default', function (): void {
    [$sent, $content] = sendRedactionPrompt([]);

    expect($sent)->toContain('[email-1]')->toContain('[phone-1]')->not->toContain('jo@example.com')->not->toContain('7946')
        ->and($content)->toBe('Write to [email-1] now');
});

it('restores the originals in the output when the setting asks for it', function (): void {
    [, $content] = sendRedactionPrompt(['restore_redacted_output' => true]);

    expect($content)->toBe('Write to jo@example.com now');
});

it('sends prompts unmasked when redaction is off globally or for the site', function (array $config, ?int $siteId): void {
    [$sent] = sendRedactionPrompt($config, $siteId);

    expect($sent)->toContain('jo@example.com');
})->with([
    'global toggle' => [['redact_personal_data' => false], null],
    'site toggle' => [['redaction_disabled_site_ids' => [7]], 7],
]);

it('still masks a site that is not on the disabled list', function (): void {
    [$sent] = sendRedactionPrompt(['redaction_disabled_site_ids' => [7]], 8);

    expect($sent)->not->toContain('jo@example.com');
});

it('leaves date ranges and lists of years alone, even across paragraphs', function (): void {
    $text = "Run 2024-01-15 - 2024-02-20\n\n2023 2024 2025\n1,200 2,400 3,600";

    expect((new RegexPromptRedactor)->redact($text)->texts[0])->toBe($text);
});

it('neutralises a block closer written with spaces', function (): void {
    expect(substr_count(PromptData::block('content', 'x < / capell_data > y'), '</capell_data>'))->toBe(1);
});

it('gives inputs that differ only in masked values different request digests', function (): void {
    $provider = new PrismProvider(['provider' => 'anthropic', 'model' => 'claude-test']);
    $digest = fn (string $email): string => $provider->managedRequestDigest(['messages' => [['role' => 'user', 'content' => 'Mail ' . $email]]]);

    expect($digest('alice@x.com'))->not->toBe($digest('bob@y.com'))
        ->and($digest('alice@x.com'))->toBe($digest('alice@x.com'));
});

it('never serves one page restored personal data from the generation cache', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    Prism::fake([fakeRedactionResponse('Write to [email-1] now'), fakeRedactionResponse('Write to [email-1] soon')]);
    $provider = new PrismProvider(
        ['provider' => 'anthropic', 'model' => 'claude-test', 'max_retries' => 1, 'restore_redacted_output' => true],
        new AIGenerationCache('array', 600),
    );
    $chat = fn (string $email) => $provider->chat(['messages' => [['role' => 'user', 'content' => 'Mail ' . $email]]]);

    $first = $chat('alice@x.com');
    $second = $chat('bob@y.com');

    expect($first->content)->toBe('Write to alice@x.com now')
        ->and($second->content)->toBe('Write to bob@y.com soon');
});

it('refuses a model that is not on the allowed model list before calling the provider', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    $fake = Prism::fake([fakeRedactionResponse('never sent')]);
    $provider = new PrismProvider(['provider' => 'openai', 'model' => 'gpt-4o', 'max_retries' => 1, 'allowed_models' => ['mistral-eu']]);

    expect(fn () => $provider->chat(['messages' => [['role' => 'user', 'content' => 'Hi']]]))
        ->toThrow(LogicException::class, 'allowed model list');

    $fake->assertCallCount(0);
});
