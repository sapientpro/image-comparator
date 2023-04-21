<?php

namespace SapientPro\ImageComparator\Strategy;

interface HashStrategy
{
    public function hash(array $pixels): array;
}
