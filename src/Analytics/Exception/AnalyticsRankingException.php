<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Exception;

use TheCodingMachine\GraphQLite\Exceptions\GraphQLException;

/**
 * A ranking the API refuses: a list length outside the bounds. A GraphQL error with
 * `extensions.errorCode = analytics_ranking_invalid`.
 */
final class AnalyticsRankingException extends GraphQLException
{
    public const ERROR_CODE = 'analytics_ranking_invalid';

    public static function limitOutOfBounds(int $min, int $max): self
    {
        return new self(
            sprintf('The list length must be between %d and %d', $min, $max),
            400,
            null,
            'Exception',
            ['errorCode' => self::ERROR_CODE]
        );
    }
}
