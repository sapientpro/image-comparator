<?php

namespace SapientPro\ImageComparator\Tests\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use SapientPro\ImageComparator\Strategy\DifferenceHashStrategy;

class DifferenceHashStrategyTest extends TestCase
{
    private DifferenceHashStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new DifferenceHashStrategy();
    }

    public function testHash(): void
    {
        $pixels = [116, 109, 83, 85, 103, 123, 115, 81, 86, 109, 118, 83, 95, 98, 104, 78];
        $expectedHash = [1, 1, 0, 0, 0, 1, 1, 0, 0, 0, 1, 0, 0, 0, 1, 0];

        $hash = $this->strategy->hash($pixels);

        $this->assertSame($expectedHash, $hash);
    }
}
