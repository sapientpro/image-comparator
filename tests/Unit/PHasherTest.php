<?php

namespace SapientPro\PHasher\Tests\Unit;

use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SapientPro\PHasher\ImageResourceException;
use SapientPro\PHasher\PHasher;

class PHasherTest extends TestCase
{
    private PHasher $pHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pHasher = new PHasher();
    }

    #[DataProvider('similarImagesProvider')]
    public function testCompareSimilarImages(string $image1, string $image2, float $expectedPercentage): void
    {
        $result = $this->pHasher->compare($image1, $image2);

        $this->assertGreaterThanOrEqual($expectedPercentage, $result);
    }

    #[DataProvider('similarImagesProvider')]
    public function testDetectSimilarImages(string $image1, string $image2, float $expectedPercentage): void
    {
        $result = $this->pHasher->detect($image1, $image2);

        $this->assertGreaterThanOrEqual($expectedPercentage, $result);
    }

    #[DataProvider('differentImagesProvider')]
    public function testCompareDifferentImages(string $image1, string $image2, float $expectedPercentage): void
    {
        $result = $this->pHasher->compare($image1, $image2);

        $this->assertLessThan($expectedPercentage, $result);
    }

    #[DataProvider('differentImagesProvider')]
    public function testDetectDifferentImages(string $image1, string $image2, float $expectedPercentage): void
    {
        $result = $this->pHasher->detect($image1, $image2);

        $this->assertLessThan($expectedPercentage, $result);
    }

    public function testCompareShouldThrowException(): void
    {
        $this->expectException(ImageResourceException::class);
        $this->expectException(Exception::class);

        $this->pHasher->compare('image', 'image');
    }

    public function testDetectShouldThrowException(): void
    {
        $this->expectException(ImageResourceException::class);
        $this->expectException(Exception::class);

        $this->pHasher->detect('image', 'image');
    }

    public function testSquareImage(): void
    {
        $squareImage = $this->pHasher->squareImage('tests/images/flower2.jpg');

        $width = imagesx($squareImage);
        $height = imagesy($squareImage);

        $this->assertSame($width, $height);
    }

    public function testHashImage(): void
    {
        $bits = $this->pHasher->hashImage('tests/images/flower2.jpg');

        $expectedString = "1110000011110000011110000111110000111110001111101001110010011000";

        $resultString = $this->pHasher->convertHashToBinaryString($bits);

        $this->assertSame(64, count($bits));
        $this->assertSame($expectedString, $resultString);

        $differenceHashedBits = $this->pHasher->hashImage(
            'tests/images/flower2.jpg',
            hashType: PHasher::DIFFERENCE_HASH_TYPE
        );
        $this->assertSame(64, count($differenceHashedBits));

        $bits7x7 = $this->pHasher->hashImage('tests/images/flower2.jpg', size: 7);
        $this->assertSame(49, count($bits7x7));
    }

    public function testCompareResources(): void
    {
        $image1 = $this->pHasher->squareImage('tests/images/flower.jpg');
        $image2 = $this->pHasher->squareImage('tests/images/flower2.jpg');

        $result = $this->pHasher->compare($image1, $image2);

        $this->assertGreaterThan(70.00, $result);
    }

    public function testCompareHashStringsBinaryStrings(): void
    {
        $hash1 = $this->pHasher->hashImage('tests/images/forest1.jpg');
        $hashString1 = $this->pHasher->convertHashToBinaryString($hash1);

        $hash2 = $this->pHasher->hashImage('tests/images/forest1-copyrighted.jpg');
        $hashString2 = $this->pHasher->convertHashToBinaryString($hash2);

        $result = $this->pHasher->compareHashStrings($hashString1, $hashString2);

        $this->assertSame(96.9, $result);
    }

    public function testFastHashImage(): void
    {
        $bits = $this->pHasher->fastHashImage('tests/images/flower2.jpg');

        $expectedString = "1111000011110100111100000110101101111100000111001110000111100000";

        $resultString = $this->pHasher->convertHashToBinaryString($bits);

        $this->assertSame(64, count($bits));
        $this->assertSame($expectedString, $resultString);
    }

    public function testCompareArray(): void
    {
        $image1 = 'tests/images/forest1.jpg';
        $image2 = 'tests/images/forest1-copyrighted.jpg';
        $image3 = 'tests/images/rose.jpg';

        $result = $this->pHasher->compareArray($image1, ['key1' => $image2, 'key2' => $image3]);

        $this->assertArrayHasKey('key1', $result);
        $this->assertArrayHasKey('key2', $result);
    }

    public function testDetectArray(): void
    {
        $image1 = 'tests/images/forest1.jpg';
        $image2 = 'tests/images/forest1-copyrighted.jpg';
        $image3 = 'tests/images/rose.jpg';

        $result = $this->pHasher->compareArray($image1, ['key1' => $image2, 'key2' => $image3]);

        $this->assertArrayHasKey('key1', $result);
        $this->assertArrayHasKey('key2', $result);
    }

    public function testConvertToBinaryString(): void
    {
        //phpcs:ignore
        $hash = [1,1,1,0,0,0,0,0,1,1,1,1,0,0,0,0,0,1,1,1,1,0,0,0,0,1,1,1,1,1,0,0,0,0,1,1,1,1,1,0,0,0,1,1,1,1,1,0,1,0,0,1,1,1,0,0,1,0,0,1,1,0,0,0];

        $expectedString = "1110000011110000011110000111110000111110001111101001110010011000";

        $resultString = $this->pHasher->convertHashToBinaryString($hash);

        $this->assertSame($expectedString, $resultString);
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
            'Similar images URLs' => [
                'image1' => 'https://www.gstatic.com/webp/gallery/1.jpg',
                'image2' => 'https://www.gstatic.com/webp/gallery/1.webp',
                'expectedPercentage' => 90.00
            ]
        ];
    }
}
