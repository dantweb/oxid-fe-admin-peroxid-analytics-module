<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Service;

/**
 * Percentages with one decimal that add up to exactly 100 (largest remainder method): rounding each share on its own
 * would show 33.3 + 33.3 + 33.3 = 99.9. With no total there is nothing to share and every share is 0.
 */
final class ShareRounder
{
    private const TENTHS_OF_A_PERCENT = 1000;

    private const EPSILON = 1.0e-9;

    /**
     * @param list<float> $values parts of the total, all of them (the remainder included)
     * @return list<float>
     */
    public function percentages(array $values, float $total): array
    {
        if ($total <= 0.0 || $values === []) {
            return array_fill(0, count($values), 0.0);
        }
        $exact = array_map(
            static fn (float $value): float => $value / $total * self::TENTHS_OF_A_PERCENT,
            $values
        );
        $tenths = array_map(static fn (float $share): int => (int) floor($share + self::EPSILON), $exact);
        foreach ($this->indexesByRemainder($exact, $tenths, self::TENTHS_OF_A_PERCENT - array_sum($tenths)) as $index) {
            ++$tenths[$index];
        }

        return array_map(static fn (int $tenth): float => $tenth / 10, $tenths);
    }

    /**
     * @param list<float> $exact
     * @param list<int> $tenths
     * @return list<int> the indexes that get one more tenth, biggest fraction first, earlier index on a tie
     */
    private function indexesByRemainder(array $exact, array $tenths, int $leftOver): array
    {
        $remainders = [];
        foreach ($exact as $index => $share) {
            $remainders[$index] = $share - $tenths[$index];
        }
        $indexes = array_keys($remainders);
        usort(
            $indexes,
            static fn (int $left, int $right): int => [$remainders[$right], $left] <=> [$remainders[$left], $right]
        );

        return array_slice($indexes, 0, max(0, $leftOver));
    }
}
