<?php

namespace SapientPro\ImageComparator\Strategy;

class DifferenceHashStrategy implements HashStrategy
{
    public function hash(array $pixels): array
    {
        $hash = [];

        // Legendante - Added a check to use one of two hashes
        // Use the difference hash (dHash) as per Dr. Neal Krawetz
        // http://www.hackerfactor.com/blog/index.php?/archives/529-Kind-of-Like-That.html
        foreach ($pixels as $key => $pixel) {
            // Legendante - Uses the original 8*8 comparison originally suggested to Dr. Krawetz
            // not the modified 9*8 as suggested by Dr. Krawetz
            if (!isset($pixels[($key + 1)])) {
                $key = -1;
            }
            if ($pixel > $pixels[($key + 1)]) {
                $hash[] = 1;
            } else {
                $hash[] = 0;
            }
        }

        return $hash;
    }
}
