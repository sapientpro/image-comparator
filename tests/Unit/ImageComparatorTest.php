<?php

namespace SapientPro\ImageComparator\Tests\Unit;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SapientPro\ImageComparator\Enum\ImageRotationAngle;
use SapientPro\ImageComparator\ImageResourceException;
use SapientPro\ImageComparator\ImageComparator;
use SapientPro\ImageComparator\Strategy\AverageHashStrategy;
use SapientPro\ImageComparator\Strategy\DifferenceHashStrategy;

class ImageComparatorTest extends TestCase
{
    private ImageComparator $imageComparator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imageComparator = new ImageComparator();
    }

    private function withErrorsIgnored(callable $callback): void
    {
        set_error_handler(static fn (): bool => true);

        try {
            $callback();
        } finally {
            restore_error_handler();
        }
    }

    #[DataProvider('similarImagesProvider')]
    public function testCompareSimilarImages(string $image1, string $image2, float $expectedPercentage): void
    {
        $result = $this->imageComparator->compare($image1, $image2);

        $this->assertGreaterThanOrEqual($expectedPercentage, $result);
    }

    #[DataProvider('similarImagesProvider')]
    public function testDetectSimilarImages(string $image1, string $image2, float $expectedPercentage): void
    {
        $result = $this->imageComparator->detect($image1, $image2);

        $this->assertGreaterThanOrEqual($expectedPercentage, $result);
    }

    #[DataProvider('similarImagesProvider')]
    public function testDetectSimilarImagesDifferenceHash(
        string $image1,
        string $image2,
        float $expectedPercentage
    ): void {
        $this->imageComparator->setHashStrategy(new DifferenceHashStrategy());

        $result = $this->imageComparator->detect($image1, $image2);

        $this->assertGreaterThanOrEqual($expectedPercentage, $result);
    }

    /**
     * @throws ImageResourceException
     */
    #[DataProvider('differentImagesProvider')]
    public function testCompareDifferentImages(string $image1, string $image2, float $expectedPercentage): void
    {
        $result = $this->imageComparator->compare($image1, $image2);

        $this->assertLessThan($expectedPercentage, $result);
    }

    #[DataProvider('differentImagesProvider')]
    public function testDetectDifferentImages(string $image1, string $image2, float $expectedPercentage): void
    {
        $result = $this->imageComparator->detect($image1, $image2);

        $this->assertLessThan($expectedPercentage, $result);
    }

    public function testCompareRotated(): void
    {
        $image = 'tests/images/flower2.jpg';
        $imageRotated = 'tests/images/flower2-rotated.jpg';

        $result = $this->imageComparator->compare($image, $imageRotated, ImageRotationAngle::D90);

        $this->assertSame(96.363, $result);

        $result = $this->imageComparator->compare($image, $imageRotated, ImageRotationAngle::D90, 10);

        $this->assertSame(96.3377374947, $result);
    }

    public function testCompareShouldThrowException(): void
    {
        $this->expectException(ImageResourceException::class);
        $this->expectException(Exception::class);

        $this->withErrorsIgnored(fn () => $this->imageComparator->compare('image', 'image'));
    }

    public function testDetectShouldThrowException(): void
    {
        $this->expectException(ImageResourceException::class);
        $this->expectException(Exception::class);

        $this->withErrorsIgnored(fn () => $this->imageComparator->detect('image', 'image'));
    }

    public function testSquareImage(): void
    {
        $squareImage = $this->imageComparator->squareImage('tests/images/flower2.jpg');

        $width = imagesx($squareImage);
        $height = imagesy($squareImage);

        $this->assertSame($width, $height);

        $result = $this->imageComparator->compare('tests/images/flower2.jpg', $squareImage);

        $this->assertSame(36.973, $result);
    }

    public function testHashImage(): void
    {
        $bits = $this->imageComparator->hashImage('tests/images/flower2.jpg');

        $expectedString = "1110000011110000011110000111110000111110001111101001110010011000";

        $resultString = $this->imageComparator->convertHashToBinaryString($bits);

        $this->assertSame(64, count($bits));
        $this->assertSame($expectedString, $resultString);

        $this->imageComparator->setHashStrategy(new DifferenceHashStrategy());

        $differenceHashedBits = $this->imageComparator->hashImage(
            'tests/images/flower2.jpg'
        );
        $this->assertSame(64, count($differenceHashedBits));

        $this->imageComparator->setHashStrategy(new AverageHashStrategy());

        $bits7x7 = $this->imageComparator->hashImage('tests/images/flower2.jpg', size: 7);
        $this->assertSame(49, count($bits7x7));
    }

    public function testCompareResources(): void
    {
        $image1 = $this->imageComparator->squareImage('tests/images/flower.jpg');
        $image2 = $this->imageComparator->squareImage('tests/images/flower2.jpg');

        $result = $this->imageComparator->compare($image1, $image2);

        $this->assertGreaterThan(70.00, $result);
    }

    public function testCompareHashStrings(): void
    {
        $hash1 = $this->imageComparator->hashImage('tests/images/forest1.jpg');
        $hashString1 = $this->imageComparator->convertHashToBinaryString($hash1);

        $hash2 = $this->imageComparator->hashImage('tests/images/forest1-copyrighted.jpg');
        $hashString2 = $this->imageComparator->convertHashToBinaryString($hash2);

        $result = $this->imageComparator->compareHashStrings($hashString1, $hashString2);

        $this->assertGreaterThan(90.0, $result);
    }

    public function testCompareSerializedHashes(): void
    {
        $image1 = 'tests/images/forest1.jpg';
        $image2 = 'tests/images/forest1-copyrighted.jpg';

        $serializedHash1 = $this->imageComparator->serializeImageHash($image1);
        $serializedHash2 = $this->imageComparator->serializeImageHash($image2);

        $expected = $this->imageComparator->compare($image1, $image2);
        $result = $this->imageComparator->compare($serializedHash1, $serializedHash2);

        $this->assertEquals($expected, $result);
    }

    public function testCompareMixedSerializedHashAndImageInput(): void
    {
        $image1 = 'tests/images/forest1.jpg';
        $image2 = 'tests/images/forest1-copyrighted.jpg';

        $serializedHash1 = $this->imageComparator->serializeImageHash($image1);
        $serializedHash2 = $this->imageComparator->serializeImageHash($image2);

        $expected = $this->imageComparator->compare($image1, $image2);
        $sourceSerializedResult = $this->imageComparator->compare($serializedHash1, $image2);
        $comparedSerializedResult = $this->imageComparator->compare($image1, $serializedHash2);

        $this->assertEquals($expected, $sourceSerializedResult);
        $this->assertEquals($expected, $comparedSerializedResult);
    }

    public function testCompareArray(): void
    {
        $image1 = 'tests/images/forest1.jpg';
        $image2 = 'tests/images/forest1-copyrighted.jpg';
        $image3 = 'tests/images/rose.jpg';

        $result = $this->imageComparator->compareArray($image1, ['key1' => $image2, 'key2' => $image3]);

        $this->assertArrayHasKey('key1', $result);
        $this->assertArrayHasKey('key2', $result);
    }

    public function testCompareArrayWithSerializedHashes(): void
    {
        $image1 = 'tests/images/forest1.jpg';
        $image2 = 'tests/images/forest1-copyrighted.jpg';
        $image3 = 'tests/images/rose.jpg';

        $sourceSerializedHash = $this->imageComparator->serializeImageHash($image1);
        $comparedSerializedHash = $this->imageComparator->serializeImageHash($image2);

        $result = $this->imageComparator->compareArray(
            $sourceSerializedHash,
            ['key1' => $comparedSerializedHash, 'key2' => $image3]
        );

        $expected = [
            'key1' => $this->imageComparator->compare($image1, $image2),
            'key2' => $this->imageComparator->compare($image1, $image3),
        ];

        $this->assertEquals($expected, $result);
    }

    public function testDetectArray(): void
    {
        $image1 = 'tests/images/forest1.jpg';
        $image2 = 'tests/images/forest1-copyrighted.jpg';
        $image3 = 'tests/images/rose.jpg';

        $result = $this->imageComparator->detectArray($image1, ['key1' => $image2, 'key2' => $image3]);

        $this->assertArrayHasKey('key1', $result);
        $this->assertArrayHasKey('key2', $result);
    }

    public function testConvertToBinaryString(): void
    {
        //phpcs:ignore
        $hash = [1,1,1,0,0,0,0,0,1,1,1,1,0,0,0,0,0,1,1,1,1,0,0,0,0,1,1,1,1,1,0,0,0,0,1,1,1,1,1,0,0,0,1,1,1,1,1,0,1,0,0,1,1,1,0,0,1,0,0,1,1,0,0,0];

        $expectedString = "1110000011110000011110000111110000111110001111101001110010011000";

        $resultString = $this->imageComparator->convertHashToBinaryString($hash);

        $this->assertSame($expectedString, $resultString);
    }

    public function testCompareMany(): void
    {
        $image = 'test';
        $filePath = 'results.csv';
        $file = fopen($filePath, 'w');
        fputcsv($file, ['Image_First', 'Image_Second', 'First_Result'], escape: '\\');

        $results = [];
        for ($i = 1; $i <= 50; $i++) {
            $image1 = $image . '.jpeg';
            $image2 = $image . $i . '.jpeg';
            $result = $this->imageComparator->compare(
                'tests/images/test_comparison/' . $image1,
                'tests/images/test_comparison/' . $image2,
                ImageRotationAngle::D0,
                20
            );
            $results[] = $result;
            fputcsv($file, [$image1, $image2, $result], escape: '\\');
        }

        fclose($file);
        $this->assertCount(50, $results);

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->assertFileDoesNotExist($filePath);
    }

    public function testCompareNonImageFiles(): void
    {
        $this->expectException(ImageResourceException::class);

        $this->withErrorsIgnored(
            fn () => $this->imageComparator->compare('tests/files/document.txt', 'tests/files/document2.txt')
        );
    }

    public function testCompareShouldThrowExceptionOnMalformedSerializedHashString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->imageComparator->compare(
            'imgcmp-v1:not-a-valid-payload',
            'tests/images/forest.jpg'
        );
    }

    #[DataProvider('imageFormatsProvider')]
    public function testCompareDifferentFormats(string $image1, string $image2, float $expectedPercentage): void
    {
        $result = $this->imageComparator->compare($image1, $image2);

        $this->assertGreaterThanOrEqual($expectedPercentage, $result);
    }

    public function testCompareDifferentSizes(): void
    {
        $image1 = 'tests/images/forest.jpg';
        $image2 = 'tests/images/forest_small.jpg';

        $result = $this->imageComparator->compare($image1, $image2);

        $this->assertGreaterThan(50, $result);
    }

    public function testCompareWithBrightnessContrastAdjustment(): void
    {
        $image1 = 'tests/images/flower.jpg';
        $image2 = 'tests/images/forest_contrast.jpg';

        $result = $this->imageComparator->compare($image1, $image2);

        $this->assertGreaterThan(55, $result);
    }

    public function testCompareBlurredImages(): void
    {
        $image1 = 'tests/images/forest.jpg';
        $image2 = 'tests/images/forest_blurred.jpg';

        $result = $this->imageComparator->compare($image1, $image2);

        $this->assertGreaterThan(65.00, $result);
    }

    public function testUnsupportedImageFormat(): void
    {
        $this->expectException(ImageResourceException::class);

        $this->withErrorsIgnored(
            fn () => $this->imageComparator->compare(
                'tests/images/flower.jpg',
                'tests/images/unsupported-image-format.tif'
            )
        );
    }

    public static function differentImagesProvider(): array
    {
        return [
            'Different images with similarities' => [
                'image1' => 'tests/images/flower.jpg',
                'image2' => 'tests/images/flower2.jpg',
                'expectedPercentage' => 70.00
            ],
            'Different images' => [
                'image1' => 'tests/images/bird-yellow.jpg',
                'image2' => 'tests/images/rose.jpg',
                'expectedPercentage' => 60.00
            ],
            'Different images 2' => [
                'image1' => 'tests/images/ebay-image2.png',
                'image2' => 'tests/images/amazon-image2.png',
                'expectedPercentage' => 60.00
            ],
            'Similar image black and white' => [
                'image1' => 'tests/images/black.png',
                'image2' => 'tests/images/white.png',
                'expectedPercentage' => 1.00
            ],
            'Similar image red and blue' => [
                'image1' => 'tests/images/red.png',
                'image2' => 'tests/images/blue.png',
                'expectedPercentage' => 20
            ]
        ];
    }

    public static function similarImagesProvider(): array
    {
        return [
            'Same images' => [
                'image1' => 'tests/images/forest.jpg',
                'image2' => 'tests/images/forest-copy.jpg',
                'expectedPercentage' => 100.00
            ],
            'Similar images' => [
                'image1' => 'tests/images/forest1.jpg',
                'image2' => 'tests/images/forest1-copyrighted.jpg',
                'expectedPercentage' => 90.00
            ],
            'Similar images 2' => [
                'image1' => 'tests/images/ebay-image.png',
                'image2' => 'tests/images/amazon-image.png',
                'expectedPercentage' => 80.00
            ],
            'Similar images URLs' => [
                'image1' => 'https://www.gstatic.com/webp/gallery/1.jpg',
                'image2' => 'https://www.gstatic.com/webp/gallery/1.webp',
                'expectedPercentage' => 90.00
            ]
        ];
    }

    public static function imageFormatsProvider(): array
    {
        return [
            ['tests/images/flower.jpg', 'tests/images/flower.png', 85.00],
            ['tests/images/flower.gif', 'tests/images/flower.jpg', 90.00],
            ['tests/images/flower.webp', 'tests/images/flower.png', 85.00],
        ];
    }
}
