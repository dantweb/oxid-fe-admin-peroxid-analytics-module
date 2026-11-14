<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Infrastructure;

/** What is ranked: products (variants folded into the parent) or categories (the product's main category). */
enum RankingSubject
{
    case PRODUCTS;
    case CATEGORIES;
}
