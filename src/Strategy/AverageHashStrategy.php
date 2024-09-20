<?php

namespace SapientPro\ImageComparator\Strategy;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;

class AverageHashStrategy implements HashStrategy
{
    /**
     * @throws DivisionByZeroException
     * @throws MathException
     * @throws NumberFormatException
     */
    public function hash(array $pixels): array
    {
        $hash = [];
        $index = 0;

        $totalPixelsValue = BigDecimal::of(array_sum($pixels));
        $pixelCount = BigDecimal::of(count($pixels));

        $averagePixelsValue = $totalPixelsValue->dividedBy($pixelCount, 0, RoundingMode::DOWN);

        foreach ($pixels as $pixel) {
            if (BigDecimal::of($pixel)->isGreaterThan($averagePixelsValue)) {
                $hash[$index] = 1;
            } else {
                $hash[$index] = 0;
            }
            $index += 1;
        }

        return $hash;
    }
}
