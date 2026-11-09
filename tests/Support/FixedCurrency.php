<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Support;

use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\ShopCurrencyInterface;

final class FixedCurrency implements ShopCurrencyInterface
{
    public function __construct(private readonly string $code = 'EUR')
    {
    }

    public function code(): string
    {
        return $this->code;
    }
}
