<?php

namespace SapientPro\ImageComparator\Enum;

enum ImageRotationAngle: int
{
    case D0 = 0;

    case D90 = 90;

    case D180 = 180;

    case D270 = 270;

    public function rotatePixel(int $x, int $y, int $height, int $width): array
    {
        $pixel = [];

        switch ($this) {
            case ImageRotationAngle::D0:
                $rx = $x;
                $ry = $y;
                break;
            case ImageRotationAngle::D90:
                $rx = (($height - 1) - $y);
                $ry = $x;
                break;
            case ImageRotationAngle::D180:
                $rx = ($width - $x) - 1;
                $ry = ($height - 1) - $y;
                break;
            case ImageRotationAngle::D270:
                $rx = $y;
                $ry = ($height - $x) - 1;
                break;
        }

        $pixel['rx'] = $rx;
        $pixel['ry'] = $ry;

        return $pixel;
    }
}
