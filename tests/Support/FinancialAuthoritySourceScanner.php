<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Tests\Support;

final class FinancialAuthoritySourceScanner
{
    /** @var list<string> */
    private const array FORBIDDEN = ['spend', 'cost', 'pricing', 'price', 'wallet', 'journal', 'balance', 'refund', 'entitlement', 'reservefunds', 'functionreserve', 'functionrelease'];

    /** @var array<string, array<string, int>> */
    private const array ALLOWED_MEMBERS = [
        'Data/Ai/ProviderCallAuthorizationData.php' => ['reservationReference' => 3, 'reservation_reference' => 3],
        'Data/Ai/TerminalSettlementResultData.php' => ['releasedAmount' => 4, 'released_amount' => 3],
        'Providers/AIOrchestratorServiceProvider.php' => ['2026_06_08_000001_add_cost_fields_to_ai_generation_histories_table' => 1],
    ];

    /** @return list<string> */
    public static function violations(string $relativePath, string $source): array
    {
        foreach (self::ALLOWED_MEMBERS[$relativePath] ?? [] as $member => $expectedOccurrences) {
            if (substr_count($source, $member) !== $expectedOccurrences) {
                return ['invalid_allowed_member_shape'];
            }
            $source = str_replace($member, '', $source);
        }
        $normalized = self::normalize($source);

        return array_values(array_filter(self::FORBIDDEN, static fn (string $symbol): bool => str_contains($normalized, $symbol)));
    }

    public static function normalize(string $source): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $source));
    }
}
