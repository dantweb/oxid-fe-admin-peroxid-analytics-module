<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\DataType;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

/**
 * The numbers of one bucket (or the totals of the period): orders placed, their gross total, customers who
 * registered, and the two ratios the admin's "relative" view shows. A bucket without data has zeros; a ratio with
 * no customers is 0.
 */
#[Type(name: 'AdminAnalyticsBucket')]
final class AdminAnalyticsBucket
{
    public function __construct(
        private readonly string $key,
        private readonly int $orders,
        private readonly float $sales,
        private readonly int $customers
    ) {
    }

    public static function empty(string $key): self
    {
        return new self($key, 0, 0.0, 0);
    }

    public function plus(int $orders, float $sales, int $customers): self
    {
        return new self(
            $this->key,
            $this->orders + $orders,
            round($this->sales + $sales, 2),
            $this->customers + $customers
        );
    }

    /** `YYYY-MM-DD`: the day, or the Monday of the ISO week; `total` for the totals of the period. */
    #[Field]
    public function key(): string
    {
        return $this->key;
    }

    #[Field]
    public function orders(): int
    {
        return $this->orders;
    }

    /** Gross order totals (`oxorder.OXTOTALORDERSUM`) in the shop's currency, cancelled orders excluded. */
    #[Field]
    public function sales(): float
    {
        return $this->sales;
    }

    /** Customers who registered in the bucket (`oxuser.OXCREATE`, customer accounts only). */
    #[Field]
    public function customers(): int
    {
        return $this->customers;
    }

    #[Field]
    public function ordersPerCustomer(): float
    {
        return $this->customers === 0 ? 0.0 : round($this->orders / $this->customers, 4);
    }

    #[Field]
    public function salesPerCustomer(): float
    {
        return $this->customers === 0 ? 0.0 : round($this->sales / $this->customers, 2);
    }
}
