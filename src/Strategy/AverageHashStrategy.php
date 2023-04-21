<?php

namespace SapientPro\ImageComparator\Strategy;

class AverageHashStrategy implements HashStrategy
{
    public function hash(array $pixels): array
    {
        $hash = [];
        $index = 0;
        $averagePixelsValue = floor(array_sum($pixels) / count($pixels));

        foreach ($pixels as $pixel) {
            if ($pixel > $averagePixelsValue) {
                $hash[$index] = 1;
            } else {
                $hash[$index] = 0;
            }
            $index += 1;
        }

        return $hash;
    }
}
