<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Unit\Analytics;

use Dantweb\PeroxidAnalytics\Analytics\Service\ShareRounder;
use PHPUnit\Framework\TestCase;

final class ShareRounderTest extends TestCase
{
    public function testExactSharesStayAsTheyAre(): void
    {
        $this->assertSame([50.0, 30.0, 20.0], (new ShareRounder())->percentages([50.0, 30.0, 20.0], 100.0));
    }

    public function testRoundingToOneDecimalKeepsTheSumAtOneHundred(): void
    {
        $shares = (new ShareRounder())->percentages([1.0, 1.0, 1.0], 3.0);

        $this->assertSame([33.4, 33.3, 33.3], $shares);
        $this->assertEqualsWithDelta(100.0, array_sum($shares), 0.0001);
    }

    public function testTheLargestRemainderGetsTheLeftOverTenth(): void
    {
        // 2/3 = 66.666..., 1/3 = 33.333...: the first remainder (.6) beats (.3)
        $this->assertSame([66.7, 33.3], (new ShareRounder())->percentages([2.0, 1.0], 3.0));
    }

    public function testNoTotalMeansNoShares(): void
    {
        $this->assertSame([0.0, 0.0], (new ShareRounder())->percentages([0.0, 0.0], 0.0));
    }

    public function testNoValuesMeansNoShares(): void
    {
        $this->assertSame([], (new ShareRounder())->percentages([], 10.0));
    }
}
