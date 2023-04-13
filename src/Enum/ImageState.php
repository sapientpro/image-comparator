<?php

namespace SapientPro\PHasher\Enum;

enum ImageState: int
{
    case DEFAULT = 0;
    case MIRRORED = 1;
    case FLIPPED = 2;
    case MIRRORED_AND_FLIPPED = 3;
}
