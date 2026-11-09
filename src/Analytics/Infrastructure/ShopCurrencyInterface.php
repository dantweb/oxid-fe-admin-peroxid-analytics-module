<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Infrastructure;

/**
 * The currency the sales are summed in: the shop's default currency (every order total is stored in it).
 */
interface ShopCurrencyInterface
{
    /** ISO 4217 code, e.g. `EUR`. */
    public function code(): string;
}
