<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Exception;

use TheCodingMachine\GraphQLite\Exceptions\GraphQLException;

/**
 * A period the API refuses: `to` before `from`, or longer than a year. A GraphQL error with
 * `extensions.errorCode = analytics_range_invalid`, as every refusal of the admin API names its reason.
 */
final class AnalyticsRangeException extends GraphQLException
{
    public const ERROR_CODE = 'analytics_range_invalid';

    public static function toBeforeFrom(): self
    {
        return new self('The period ends before it starts', 400, null, 'Exception', ['errorCode' => self::ERROR_CODE]);
    }

    public static function tooLong(int $maxDays): self
    {
        return new self(
            sprintf('The period is longer than %d days', $maxDays),
            400,
            null,
            'Exception',
            ['errorCode' => self::ERROR_CODE]
        );
    }
}
