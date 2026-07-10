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
    private const SERIALIZED_HASH_PREFIX = 'imgcmp-v1';

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
     * @param GdImage|string $sourceImage Image source or serialized hash string
     * @param GdImage|string $comparedImage Image source or serialized hash string
     * @param ImageRotationAngle $rotation
     * @param int $precision
     * @return float
     * @throws InvalidArgumentException
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
        $sourcePayload = $this->normalizeComparableInput($sourceImage);
        $comparedPayload = $this->normalizeComparableInput($comparedImage, $rotation);
        $similarityColor = $this->calculateSimilarity($sourcePayload['color'], $comparedPayload['color']);

        return $this->compareHashes($sourcePayload['hash'], $comparedPayload['hash'], $precision, $similarityColor);
    }

    /**
     * Hash source image and each image in the array.
     * Return an array of indexes of similarities as a percentage.
     *
     * @param GdImage|string $sourceImage Image source or serialized hash string
     * @param (GdImage|string)[] $images Image sources or serialized hash strings
     * @param ImageRotationAngle $rotation
     * @param int $precision
     * @return array
     * @throws InvalidArgumentException
     * @throws NumberFormatException|ImageResourceException
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
     * Create a serialized hash string that contains hash bits and average RGB.
     *
     * Format: imgcmp-v1:<binary_hash>:<r>:<g>:<b>
     *
     * @throws DivisionByZeroException
     * @throws RoundingNecessaryException
     * @throws MathException
     * @throws NumberFormatException|ImageResourceException
     */
    public function serializeImageHash(GdImage|string $image, int $size = 8): string
    {
        $hashBits = $this->hashImage($image, ImageRotationAngle::D0, $size);
        $hashString = $this->convertHashToBinaryString($hashBits);
        $averageColor = $this->getAverageRGB($image, $size);

        return sprintf(
            '%s:%s:%d:%d:%d',
            self::SERIALIZED_HASH_PREFIX,
            $hashString,
            $averageColor['r'],
            $averageColor['g'],
            $averageColor['b']
        );
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

        $percentage = $similarity->dividedBy($hashLength, $precision, RoundingMode::HalfUp)
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
     * @param GdImage|string $image
     * @param ImageRotationAngle $rotation Create the hash as if the image were rotated by this value.
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
            $y = $width->minus($height)->dividedBy(BigDecimal::of(2), 0, RoundingMode::HalfUp)
                ->toInt();
            $xRect = BigDecimal::zero()->toInt();
            $yRect = $width->minus($height)->dividedBy(BigDecimal::of(2), 0, RoundingMode::HalfUp)
                ->plus($height)
                ->toInt();
            $thumbSize = $width->toInt();
        } else {
            $x = $height->minus($width)->dividedBy(BigDecimal::of(2), 0, RoundingMode::HalfUp)
                ->toInt();
            $y = BigDecimal::zero()->toInt();
            $xRect = $height->minus($width)->dividedBy(BigDecimal::of(2), 0, RoundingMode::HalfUp)
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
     * @param array $hash
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

        $normalizedImage = imagecreatefromstring($imageData);

        if (false === $normalizedImage) {
            throw new ImageResourceException('Could not create an image resource from file');
        }

        return $normalizedImage;
    }

    /**
     * Normalize comparable input into hash bits and average RGB values.
     * Accepts either an image source/resource or a serialized hash payload.
     *
     * @param GdImage|string $image
     * @param ImageRotationAngle $rotation
     * @return array{hash: int[], color: array{r: int, g: int, b: int}}
     * @throws DivisionByZeroException
     * @throws RoundingNecessaryException
     * @throws MathException
     * @throws NumberFormatException|ImageResourceException
     */
    private function normalizeComparableInput(
        GdImage|string $image,
        ImageRotationAngle $rotation = ImageRotationAngle::D0
    ): array {
        if (is_string($image) && $this->isSerializedImageHash($image)) {
            if ($rotation !== ImageRotationAngle::D0) {
                throw new InvalidArgumentException(
                    'Rotation is not supported for serialized hash strings. Use D0 or provide image sources.'
                );
            }

            return $this->parseSerializedImageHash($image);
        }

        return [
            'hash' => $this->hashImage($image, $rotation),
            'color' => $this->getAverageRGB($image),
        ];
    }

    /**
     * Check whether the given value is a serialized hash payload.
     *
     * @param string $value
     * @return bool
     */
    private function isSerializedImageHash(string $value): bool
    {
        return str_starts_with($value, self::SERIALIZED_HASH_PREFIX . ':');
    }

    /**
     * Parse a serialized hash payload into hash bits and RGB channels.
     *
     * @param string $serializedHash
     * @return array{hash: int[], color: array{r: int, g: int, b: int}}
     * @throws InvalidArgumentException
     */
    private function parseSerializedImageHash(string $serializedHash): array
    {
        $pattern = '/^' . preg_quote(self::SERIALIZED_HASH_PREFIX, '/')
            . ':(?<hash>[01]+):(?<r>\d{1,3}):(?<g>\d{1,3}):(?<b>\d{1,3})$/';

        if (preg_match($pattern, $serializedHash, $matches) !== 1) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid serialized hash format. Expected "%s:<binary_hash>:<r>:<g>:<b>".',
                    self::SERIALIZED_HASH_PREFIX
                )
            );
        }

        $color = [
            'r' => (int)$matches['r'],
            'g' => (int)$matches['g'],
            'b' => (int)$matches['b'],
        ];

        foreach ($color as $channel => $value) {
            if ($value < 0 || $value > 255) {
                throw new InvalidArgumentException(
                    sprintf('Invalid %s channel value in serialized hash. Value must be between 0 and 255.', $channel)
                );
            }
        }

        return [
            'hash' => array_map('intval', str_split($matches['hash'])),
            'color' => $color,
        ];
    }

    /**
     * @throws DivisionByZeroException
     * @throws RoundingNecessaryException
     * @throws MathException
     * @throws NumberFormatException
     */
    private function compareHashes(array $hash1, array $hash2, int $precision, float $colorSimilarity): float
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

        $hashSimilarity = $similarity->dividedBy($totalBits, $precision, RoundingMode::HalfUp)
            ->multipliedBy(BigDecimal::of(100));
        $hashSimilarity = BigDecimal::of($hashSimilarity)->multipliedBy((string)$colorSimilarity);
        $hashSimilarity = $hashSimilarity->toScale($precision, RoundingMode::HalfUp);

        return $hashSimilarity->toFloat();
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
                    ->toScale(0, RoundingMode::HalfUp);

                $pixels[] = $grayScale->toInt();
            }
        }

        return $pixels;
    }

    private function calculateRGBDistance(array $color1, array $color2): float
    {
        $r1 = $color1['r'];
        $g1 = $color1['g'];
        $b1 = $color1['b'];

        $r2 = $color2['r'];
        $g2 = $color2['g'];
        $b2 = $color2['b'];

        return sqrt(pow($r1 - $r2, 2) + pow($g1 - $g2, 2) + pow($b1 - $b2, 2));
    }

    private function calculateSimilarity(array $color1, array $color2): float
    {
        $maxRGBDifference = sqrt(pow(255, 2) + pow(255, 2) + pow(255, 2));
        $distance = $this->calculateRGBDistance($color1, $color2);

        return 1 - ($distance / $maxRGBDifference);
    }

    /**
     * @throws ImageResourceException
     */
    private function getAverageRGB(
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
