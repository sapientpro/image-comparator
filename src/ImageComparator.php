<?php

namespace SapientPro\ImageComparator;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use GdImage;
use InvalidArgumentException;
use SapientPro\ImageComparator\Enum\ImageRotationAngle;
use SapientPro\ImageComparator\Strategy\AverageHashStrategy;
use SapientPro\ImageComparator\Strategy\HashStrategy;

class ImageComparator
{
    private HashStrategy $hashStrategy;

    public function __construct()
    {
        $this->hashStrategy = new AverageHashStrategy();
    }

    public function setHashStrategy(HashStrategy $hashStrategy): void
    {
        $this->hashStrategy = $hashStrategy;
    }

    /**
     * Hash two images and return an index of their similarly as a percentage.
     *
     * @param  GdImage|string  $sourceImage
     * @param  GdImage|string  $comparedImage
     * @param  ImageRotationAngle  $rotation
     * @param  int  $precision
     * @return float
     * @throws DivisionByZeroException
     * @throws RoundingNecessaryException
     * @throws MathException
     * @throws NumberFormatException|ImageResourceException
     */
    public function compare(
        GdImage|string $sourceImage,
        GdImage|string $comparedImage,
        ImageRotationAngle $rotation = ImageRotationAngle::D0,
        int $precision = 3
    ): float {
        $hash1 = $this->hashImage($sourceImage); // this one should never be rotated
        $hash2 = $this->hashImage($comparedImage, $rotation);
        $color1 = $this->getAverageRGB($sourceImage);
        $color2 = $this->getAverageRGB($comparedImage);
        $similarityColor = $this->calculateSimilarity($color1, $color2);

        return $this->compareHashes($hash1, $hash2, $precision, $similarityColor);
    }

    /**
     * Hash source image and each image in the array.
     * Return an array of indexes of similarities as a percentage.
     *
     * @param  GdImage|string  $sourceImage
     * @param  (GdImage|string)[]  $images
     * @param  ImageRotationAngle  $rotation
     * @param  int  $precision
     * @return array
     * @throws ImageResourceException
     */
    public function compareArray(
        GdImage|string $sourceImage,
        array $images,
        ImageRotationAngle $rotation = ImageRotationAngle::D0,
        int $precision = 3
    ): array {
        $similarityPercentages = [];

        foreach ($images as $key => $comparedImage) {
            $similarityPercentages[$key] = $this->compare($sourceImage, $comparedImage, $rotation, $precision);
        }

        return $similarityPercentages;
    }

    /**
     * Compare hash strings (no rotation).
     * This assumes the strings will be the same length, which they will be as hashes.
     *
     * @param string $hash1
     * @param string $hash2
     * @param int $precision
     * @return float
     * @throws RoundingNecessaryException
     * @throws MathException
     */
    public function compareHashStrings(string $hash1, string $hash2, int $precision = 3): float
    {
        $hashLength = BigDecimal::of(strlen($hash1));
        $similarity = $hashLength;

        for ($i = 0; $i < $hashLength->toInt(); $i++) {
            if ($hash1[$i] !== $hash2[$i]) {
                $similarity = $similarity->minus(BigDecimal::one());
            }
        }

        $percentage = $similarity->dividedBy($hashLength, $precision, RoundingMode::HALF_UP)
            ->multipliedBy(100);

        return $percentage->toFloat();
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
        int $precision = 3
    ): float {
        $highestSimilarityPercentage = 0;

        foreach (ImageRotationAngle::cases() as $rotation) {
            $similarity = $this->compare($sourceImage, $comparedImage, $rotation, $precision);

            if ($similarity > $highestSimilarityPercentage) {
                $highestSimilarityPercentage = $similarity;
            }
        }

        return $highestSimilarityPercentage;
    }

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
    public function detectArray(GdImage|string $sourceImage, array $images, int $precision = 3): array
    {
        $similarityPercentages = [];

        foreach ($images as $key => $comparedImage) {
            $similarityPercentages[$key] = $this->detect($sourceImage, $comparedImage, $precision);
        }

        return $similarityPercentages;
    }

    /**
     * Build a perceptual hash out of an image. Just uses averaging because it's faster.
     * The hash is stored as an array of bits instead of a string.
     * http://www.hackerfactor.com/blog/index.php?/archives/432-Looks-Like-It.html
     *
     * @param  GdImage|string  $image
     * @param  ImageRotationAngle  $rotation  Create the hash as if the image were rotated by this value.
     * Default is 0, allowed values are 90, 180, 270.
     * @param int $size the size of the thumbnail created from the original image.
     * The hash will be the square of this (so a value of 8 will build a hash out of 8x8 image, of 64 bits.)
     * @return array
     * @throws DivisionByZeroException
     * @throws RoundingNecessaryException
     * @throws MathException
     * @throws NumberFormatException|ImageResourceException
     */
    public function hashImage(
        GdImage|string $image,
        ImageRotationAngle $rotation = ImageRotationAngle::D0,
        int $size = 8
    ): array {
        $image = $this->normalizeAsResource($image);
        $imageCached = imagecreatetruecolor($size, $size);

        imagecopyresampled($imageCached, $image, 0, 0, 0, 0, $size, $size, imagesx($image), imagesy($image));
        imagecopymergegray($imageCached, $image, 0, 0, 0, 0, $size, $size, 50);

        $width = imagesx($imageCached);
        $height = imagesy($imageCached);

        $pixels = $this->processImagePixels($imageCached, $size, $height, $width, $rotation);

        return $this->hashStrategy->hash($pixels);
    }

    /**
     * Make an image a square and return the resource
     *
     * @param string $image
     * @return GdImage|false
     * @throws DivisionByZeroException
     * @throws RoundingNecessaryException
     * @throws MathException
     * @throws NumberFormatException|ImageResourceException
     */
    public function squareImage(string $image): GdImage|false
    {
        $imageResource = $this->normalizeAsResource($image);

        $width = BigDecimal::of(imagesx($imageResource));
        $height = BigDecimal::of(imagesy($imageResource));

        // calculating the part of the image to use for new image
        if ($width->isGreaterThan($height)) {
            $x = BigDecimal::zero()->toInt();
            $y = $width->minus($height)->dividedBy(BigDecimal::of(2), 0, RoundingMode::HALF_UP)
                ->toInt();
            $xRect = BigDecimal::zero()->toInt();
            $yRect = $width->minus($height)->dividedBy(BigDecimal::of(2), 0, RoundingMode::HALF_UP)
                ->plus($height)
                ->toInt();
            $thumbSize = $width->toInt();
        } else {
            $x = $height->minus($width)->dividedBy(BigDecimal::of(2), 0, RoundingMode::HALF_UP)
                ->toInt();
            $y = BigDecimal::zero()->toInt();
            $xRect = $height->minus($width)->dividedBy(BigDecimal::of(2), 0, RoundingMode::HALF_UP)
                ->plus($width)
                ->toInt();
            $yRect = BigDecimal::zero()->toInt();
            $thumbSize = $height->toInt();
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

    /**
     * @throws DivisionByZeroException
     * @throws RoundingNecessaryException
     * @throws MathException
     * @throws NumberFormatException
     */
    private function compareHashes(array $hash1, array $hash2, int $precision, float $similarColor): float
    {
        if (count($hash1) !== count($hash2)) {
            throw new InvalidArgumentException('Hashes must be of the same length.');
        }

        $totalBits = count($hash1);
        $similarity = BigDecimal::of($totalBits);

        foreach ($hash1 as $key => $bit) {
            if ($bit !== $hash2[$key]) {
                $similarity = $similarity->minus(BigDecimal::one());
            }
        }

        $percentage = $similarity->dividedBy($totalBits, $precision, RoundingMode::HALF_UP)
            ->multipliedBy(BigDecimal::of(100));
        $percentage = BigDecimal::of($percentage)->multipliedBy($similarColor);
        $percentage = $percentage->toScale($precision, RoundingMode::HALF_UP);

        return $percentage->toFloat();
    }

    /**
     * @throws DivisionByZeroException
     * @throws RoundingNecessaryException
     * @throws MathException
     * @throws NumberFormatException
     */
    private function processImagePixels(
        GdImage $imageResource,
        int $size,
        int $height,
        int $width,
        ImageRotationAngle $rotation = ImageRotationAngle::D0
    ): array {
        $pixels = [];

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
//                  Instead of rotating the image, we'll rotate the position of the pixels.
//                  This will allow us to generate a hash
//                  that can be used to judge if one image is a rotated version of the other,
//                  without actually creating an extra image resource.
//                  This currently only works at all for 90 degree rotations.
                $pixelPosition = $rotation->rotatePixel($x, $y, $height, $width);

                $rgb = imagecolorsforindex(
                    $imageResource,
                    imagecolorat($imageResource, $pixelPosition['rx'], $pixelPosition['ry'])
                );

                $r = BigDecimal::of($rgb['red']);
                $g = BigDecimal::of($rgb['green']);
                $b = BigDecimal::of($rgb['blue']);

                // rgb to grayscale conversion
                $grayScale = $r->multipliedBy('0.299')
                    ->plus($g->multipliedBy('0.587'))
                    ->plus($b->multipliedBy('0.114'))
                    ->toScale(0, RoundingMode::HALF_UP);

                $pixels[] = $grayScale->toInt();
            }
        }

        return $pixels;
    }

    public function calculateRGBDistance($color1, $color2): float
    {
        $r1 = $color1['r'];
        $g1 = $color1['g'];
        $b1 = $color1['b'];

        $r2 = $color2['r'];
        $g2 = $color2['g'];
        $b2 = $color2['b'];

        return sqrt(pow($r1 - $r2, 2) + pow($g1 - $g2, 2) + pow($b1 - $b2, 2));
    }

    public function calculateSimilarity($color1, $color2): float
    {
        $maxRGBDifference = sqrt(pow(255, 2) + pow(255, 2) + pow(255, 2));
        $distance = $this->calculateRGBDistance($color1, $color2);

        return 1 - ($distance / $maxRGBDifference);
    }

    /**
     * @throws ImageResourceException
     */
    public function getAverageRGB(
        GdImage|string $image,
        int $size = 8
    ): array {
        $image = $this->normalizeAsResource($image);
        $imageResized = imagescale($image, $size, $size);

        $width = imagesx($imageResized);
        $height = imagesy($imageResized);

        $totalRed = $totalGreen = $totalBlue = 0;
        $totalPixels = $width * $height;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $colorIndex = imagecolorat($imageResized, $x, $y);

                $red = ($colorIndex >> 16) & 0xFF;
                $green = ($colorIndex >> 8) & 0xFF;
                $blue = $colorIndex & 0xFF;

                $totalRed += $red;
                $totalGreen += $green;
                $totalBlue += $blue;
            }
        }

        return [
            'r' => round($totalRed / $totalPixels),
            'g' => round($totalGreen / $totalPixels),
            'b' => round($totalBlue / $totalPixels),
        ];
    }
}
