<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Infrastructure;

use OxidEsales\Eshop\Core\Registry;

/**
 * The shop's active currency object (`Config::getActShopCurrencyObject()`), its `name` is the ISO code.
 */
final class OxidShopCurrency implements ShopCurrencyInterface
{
    private const FALLBACK = 'EUR';

    public function code(): string
    {
        $currency = Registry::getConfig()->getActShopCurrencyObject();
        $code = (string) ($currency->name ?? '');

        return $code === '' ? self::FALLBACK : $code;
    }
}
