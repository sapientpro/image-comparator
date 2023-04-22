<?php

namespace SapientPro\ImageComparator\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SapientPro\ImageComparator\Enum\ImageRotationAngle;

class ImageRotationAngleTest extends TestCase
{
    #[DataProvider('rotatePixelProvider')]
    public function testRotatePixel(ImageRotationAngle $angle, array $expectedPositions): void
    {
        $x = 0;
        $y = 0;
        $width = 16;
        $height = 16;

        $pixelPositions = $angle->rotatePixel($x, $y, $width, $height);

        $this->assertSame($expectedPositions, $pixelPositions);
    }

    public static function rotatePixelProvider(): array
    {
        return [
            '0 degrees - no rotation' => [
                'angle' => ImageRotationAngle::D0,
                'expectedPositions' => ['rx' => 0, 'ry' => 0]
            ],
            '90 degrees' => [
                'angle' => ImageRotationAngle::D90,
                'expectedPositions' => ['rx' => 15, 'ry' => 0]
            ],
            '180 degrees' => [
                'angle' => ImageRotationAngle::D180,
                'expectedPositions' => ['rx' => 15, 'ry' => 15]
            ],
            '270 degrees' => [
                'angle' => ImageRotationAngle::D270,
                'expectedPositions' => ['rx' => 0, 'ry' => 15]
            ]
        ];
    }
}
