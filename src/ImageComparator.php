<?php

namespace SapientPro\ImageComparator;

use GdImage;

class ImageComparator
{
    public const AVERAGE_HASH_TYPE = 'aHash';
    public const DIFFERENCE_HASH_TYPE = 'dHash';

    /**
     * Array of images multi-pass comparison
     * The compared image is being rotated by 90 degrees and compared for each rotation.
     * Returns the highest match after comparing rotations for each array element.
     *
     * @param GdImage|string $sourceImage
     * @param (GdImage|string)[] $images
     * @param int $precision
     * @return array
     * @throws ImageResourceException
     */
    public function detectArray(GdImage|string $sourceImage, array $images, int $precision = 1): array
    {
        $result = [];

        foreach ($images as $key => $comparedImage) {
            $result[$key] = $this->detect($sourceImage, $comparedImage, $precision);
        }

        return $result;
    }

    /**
     * Multi-pass comparison - the compared image is being rotated by 90 degrees and compared for each rotation.
     * Returns the highest match after comparing rotations.
     *
     * @param GdImage|string $sourceImage
     * @param GdImage|string $comparedImage
     * @param int $precision
     * @return float
     * @throws ImageResourceException
     */
    public function detect(
        GdImage|string $sourceImage,
        GdImage|string $comparedImage,
        int $precision = 1
    ): float {
        $result = 0;

        for ($rotation = 0; $rotation <= 270; $rotation += 90) {
            $newResult = $this->compare($sourceImage, $comparedImage, $rotation, $precision);

            if ($newResult > $result) {
                $result = $newResult;
            }
        }

        return $result;
    }

    /**
     * Compare hash strings (no rotation).
     * This assumes the strings will be the same length, which they will be as hashes.
     *
     * @param string $hash1
     * @param string $hash2
     * @param int $precision
     * @return float
     */
    public function compareHashStrings(string $hash1, string $hash2, int $precision = 1): float
    {
        $similarity = strlen($hash1);

        // take the hamming distance between the strings.
        for ($i = 0; $i < strlen($hash1); $i++) {
            if ($hash1[$i] !== $hash2[$i]) {
                $similarity--;
            }
        }

        return round(($similarity / strlen($hash1) * 100), $precision);
    }

    /**
     * Hash source image and each image in the array.
     * Return an array of indexes of similarities as a percentage.
     *
     * @param GdImage|string $sourceImage
     * @param (GdImage|string)[] $images
     * @param int $rotation
     * @param int $precision
     * @return array
     * @throws ImageResourceException
     */
    public function compareArray(
        GdImage|string $sourceImage,
        array $images,
        int $rotation = 0,
        int $precision = 1
    ): array {
        $result = [];

        foreach ($images as $key => $comparedImage) {
            $result[$key] = $this->compare($sourceImage, $comparedImage, $rotation, $precision);
        }

        return $result;
    }

    /**
     * Hash two images and return an index of their similarly as a percentage.
     *
     * @param GdImage|string $sourceImage
     * @param GdImage|string $comparedImage
     * @param int $rotation
     * @param int $precision
     * @return float
     * @throws ImageResourceException
     */
    public function compare(
        GdImage|string $sourceImage,
        GdImage|string $comparedImage,
        int $rotation = 0,
        int $precision = 1
    ): float {
        $hash1 = $this->hashImage($sourceImage); // this one should never be rotated
        $hash2 = $this->hashImage($comparedImage, $rotation);

        return $this->compareHashes($hash1, $hash2, $precision);
    }

    private function compareHashes(array $hash1, array $hash2, int $precision): float
    {
        $similarity = count($hash1);

        // take the hamming distance between the hashes.
        foreach ($hash1 as $key => $val) {
            if ($val !== $hash2[$key]) {
                $similarity--;
            }
        }

        return round(($similarity / count($hash1) * 100), $precision);
    }

    /**
     * Build a perceptual hash out of an image. Just uses averaging because it's faster.
     * The hash is stored as an array of bits instead of a string.
     * http://www.hackerfactor.com/blog/index.php?/archives/432-Looks-Like-It.html
     *
     * @param GdImage|string $image Filename/path to file or GdImage instance
     * @param int $rotation Create the hash as if the image were rotated by this value.
     * Default is 0, allowed values are 90, 180, 270.
     * @param int $size the size of the thumbnail created from the original image.
     * The hash will be the square of this (so a value of 8 will build a hash out of 8x8 image, of 64 bits.)
     * @param string $hashType - hashing type: aHash or dHash
     *
     * @return array
     * @throws ImageResourceException
     */
    public function hashImage(
        GdImage|string $image,
        int $rotation = 0,
        int $size = 8,
        string $hashType = self::AVERAGE_HASH_TYPE
    ): array {
        $hash = [];

        $image = $this->normalizeAsResource($image);
        $imageCached = imagecreatetruecolor($size, $size);

        imagecopyresampled($imageCached, $image, 0, 0, 0, 0, $size, $size, imagesx($image), imagesy($image));
        imagecopymergegray($imageCached, $image, 0, 0, 0, 0, $size, $size, 50);

        $width = imagesx($imageCached);
        $height = imagesy($imageCached);

        $pixels = [];

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
//                  Instead of rotating the image, we'll rotate the position of the pixels.
//                  This will allow us to generate a hash
//                  that can be used to judge if one image is a rotated version of the other,
//                  without actually creating an extra image resource.
//                  This currently only works at all for 90 degree rotations.

                switch ($rotation) {
                    case 90:
                        $rx = (($height - 1) - $y);
                        $ry = $x;
                        break;
                    case 180:
                        $rx = ($width - $x) - 1;
                        $ry = ($height - 1) - $y;
                        break;
                    case 270:
                        $rx = $y;
                        $ry = ($height - $x) - 1;
                        break;
                    default:
                        $rx = $x;
                        $ry = $y;
                }

                $rgb = imagecolorsforindex($imageCached, imagecolorat($imageCached, $rx, $ry));

                $r = $rgb['red'];
                $g = $rgb['green'];
                $b = $rgb['blue'];

                $gs = (($r * 0.299) + ($g * 0.587) + ($b * 0.114));
                $gs = floor($gs);

                $pixels[] = $gs;
            }
        }

        $avg = $this->arrayAverage($pixels);

        // create a hash (1 for pixels above the mean, 0 for average or below)
        $index = 0;

        // Legendante - Added a check to use one of two hashes
        // Use the difference hash (dHash) as per Dr. Neal Krawetz
        // http://www.hackerfactor.com/blog/index.php?/archives/529-Kind-of-Like-That.html
        if (self::DIFFERENCE_HASH_TYPE === $hashType) {
            foreach ($pixels as $ind => $px) {
                // Legendante - Uses the original 8*8 comparison originally suggested to Dr. Krawetz
                // not the modified 9*8 as suggested by Dr. Krawetz
                if (!isset($pixels[($ind + 1)])) {
                    $ind = -1;
                }
                if ($px > $pixels[($ind + 1)]) {
                    $hash[] = 1;
                } else {
                    $hash[] = 0;
                }
            }
        } else {
            // Use the original average hash as per kennethrapp
            foreach ($pixels as $px) {
                if ($px > $avg) {
                    $hash[$index] = 1;
                } else {
                    $hash[$index] = 0;
                }
                $index += 1;
            }
        }

        return $hash;
    }

    /**
     * Heavily modified hashing from a bicubic resampling function by an unknown author here:
     * http://php.net/manual/en/function.imagecopyresampled.php#78049
     *
     * This will scale down, desaturate and hash an image entirely in memory
     * without the intermediate steps of altering the image resource and
     * re-reading pixel data, and return a perceptual hash for that image.
     * Doesn't support rotation yet and is not actually as fast as it could be due to the multiple looping.
     *
     * @param GdImage|string $image
     * @param int $size
     * @return array
     * @throws ImageResourceException
     */
    public function fastHashImage(GdImage|string $image, int $size = 8): array
    {
        $pHash = [];

        $image = $this->normalizeAsResource($image);

        $hash = [];
        $src_w = imagesx($image);
        $src_h = imagesy($image);

        $rX = $src_w / $size;
        $rY = $src_h / $size;
        $w = 0;

        for ($y = 0; $y < $size; $y++) {
            $ow = $w;
            $w = round(($y + 1) * $rY);
            $t = 0;
            for ($x = 0; $x < $size; $x++) {
                $r = $g = $b = 0;
                $a = 0;
                $ot = $t;
                $t = round(($x + 1) * $rX);
                for ($u = 0; $u < ($w - $ow); $u++) {
                    for ($p = 0; $p < ($t - $ot); $p++) {
                        $rgb = imagecolorat($image, $ot + $p, $ow + $u);

                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;

                        $gs = floor((($r * 0.299) + ($g * 0.587) + ($b * 0.114)));
                        $hash[$x][$y] = $gs;
                    }
                }
            }
        }

        // reset all the indexes.
        $nhash = [];
        $xnormal = 0;

        foreach ($hash as $xkey => $xval) {
            foreach ($hash[$xkey] as $ykey => $yval) {
                unset($hash[$xkey]);
                $nhash[$xnormal][] = $yval;
            }
            $xnormal++;
        }

        // now hash (I really need to reduce the number of loops here.)
        for ($x = 0; $x < $size; $x++) {
            $avg = floor(array_sum($nhash[$x]) / count(array_filter($nhash[$x])));

            for ($y = 0; $y < $size; $y++) {
                $rgb = $nhash[$x][$y];
                if ($rgb > $avg) {
                    $pHash[] = 1;
                } else {
                    $pHash[] = 0;
                }
            }
        }

        return $pHash;
    }

    /**
     * Make an image a square and return the resource
     *
     * @param string $image
     * @return GdImage|false
     * @throws ImageResourceException
     */
    public function squareImage(string $image): GdImage|false
    {
        $imageResource = $this->normalizeAsResource($image);

        $width = imagesx($imageResource);
        $height = imagesy($imageResource);

        // calculating the part of the image to use for new image
        if ($width > $height) {
            $x = 0;
            $y = ($width - $height) / 2;
            $xRect = 0;
            $yRect = ($width - $height) / 2 + $height;
            $smallestSide = $height;
            $thumbSize = $width;
        } else {
            $x = ($height - $width) / 2;
            $y = 0;
            $xRect = ($height - $width) / 2 + $width;
            $yRect = 0;
            $smallestSide = $width;
            $thumbSize = $height;
        }

        // copying the part into new image
        $thumb = imagecreatetruecolor($thumbSize, $thumbSize);
        // set background top / left white
        $white = imagecolorallocate($thumb, 255, 255, 255);
        imagefilledrectangle($thumb, 0, 0, $thumbSize - 1, $thumbSize - 1, $white);
        imagecopyresampled($thumb, $imageResource, $x, $y, 0, 0, $thumbSize, $thumbSize, $thumbSize, $thumbSize);
        // set background bottom / right white
        imagefilledrectangle($thumb, $xRect, $yRect, $thumbSize - 1, $thumbSize - 1, $white);

        return $thumb;
    }

    /**
     * Return binary string from an image hash created by hashImage()
     * @param  array  $hash
     * @return string
     */
    public function convertHashToBinaryString(array $hash): string
    {
        return implode('', $hash);
    }

    /**
     * Create an image resource from the file.
     * If the resource (GdImage) is supplied - return the resource
     *
     * @param string|GdImage $image - Path to file/filename or GdImage instance
     * @return GdImage
     * @throws ImageResourceException
     */
    private function normalizeAsResource(string|GdImage $image): GdImage
    {
        if ($image instanceof GdImage) {
            return $image;
        }

        $imageData = file_get_contents($image);

        if (false === $imageData) {
            throw new ImageResourceException('Could not create an image resource from file');
        }

        return imagecreatefromstring($imageData);
    }

    private function arrayAverage(array $array): float
    {
        return floor(array_sum($array) / count($array));
    }
}
