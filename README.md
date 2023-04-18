# Image Comparator

<!-- TOC -->
* [Image Comparator](#image-comparator)
  * [Installation](#installation)
    * [Prerequisites](#prerequisites)
  * [Usage](#usage)
  * [Available methods:](#available-methods)
      * [hashImage()](#hashimage)
      * [fastHashImage()](#fasthashimage)
      * [compare()](#compare)
      * [compareArray()](#comparearray)
      * [detect()](#detect)
      * [detectArray()](#detectarray)
      * [squareImage()](#squareimage)
      * [convertHashToBinaryString()](#converthashtobinarystring)
      * [compareHashStrings()](#comparehashstrings)
<!-- TOC -->

Image Comparator is a library for image comparing and hashing.

Based on https://github.com/kennethrapp/phasher package, with PHP 8 and PHPUnit support.
The original project was abandoned in November 2017.

Perceptual hashing is a method to generate a hash of an image which allows multiple images to be compared by an index of similarity.
You can find out more at [The Hacker Factor](http://www.hackerfactor.com/blog/index.php?/archives/432-Looks-Like-It.html)
and [phash.org](http://phash.org).

## Installation

### Prerequisites

* PHP 8.1 or higher
* gd extension enabled
* Composer v2 installed
* xml-writer extension enabled (dev only)
* mbstring extension enabled (dev only)

To install the library, run:

`composer require sapientpro/image-comparator`

## Usage

`ImageComparator` is the core class of the library. To get started, you can either inject it or create the instance manually:

```php
use SapientPro\ImageComparator\ImageComparator;

public function __construct(private ImageComparator $imageComparator)
```

or

```php
use SapientPro\ImageComparator\ImageComparator;

$imageComparator = new ImageComparator()
```

After that you can use one of the methods available:

```php
$imageComparator->compare('your-images/your-image1.jpg', 'your-images/your-image12.jpg');
```

If the image path can't be resolved, `ImageResourceException` will be thrown:

```php
$imageComparator->hashImage('your-images/non-existent-image.jpg'); // SapientPro\ImageComparator\ImageResourceException: Could not create an image resource from file
```

Example usage:

We have two images:
![Equals1](https://github.com/sapientpro/phasher/blob/feature/phasher-implementation/tests/images/forest1.jpg?raw=true)
![Equals2](https://github.com/sapientpro/phasher/blob/feature/phasher-implementation/tests/images/forest1-copyrighted.jpg?raw=true)

Now, let's compare them:

```php
use SapientPro\ImageComparator\ImageComparator;

$image1 = 'https://raw.githubusercontent.com/sapientpro/phasher/feature/phasher-implementation/tests/images/forest1.jpg';
$image2 = 'https://raw.githubusercontent.com/sapientpro/phasher/feature/phasher-implementation/tests/images/forest1-copyrighted.jpg'

$imageComparator = new ImageComparator();
$similarity = $imageComparator->compare($image1, $image2); //default hashing without rotation

echo $similarity; //95.33
```

The compared image can be rotated by 0 (default), 90, 180 and 270 degrees:

```php
$similarity = $imageComparator->compare($image1, $image2, 180); //compared image will be passed upside down

echo $similarity; //45.33
```

You can also use `detect()` method which will rotate the compared image and return the highest percentage of similarity:

```php
use SapientPro\ImageComparator\ImageComparator;

$image1 = 'https://raw.githubusercontent.com/sapientpro/phasher/feature/phasher-implementation/tests/images/forest1.jpg';
$image2 = 'https://raw.githubusercontent.com/sapientpro/phasher/feature/phasher-implementation/tests/images/forest1-copyrighted.jpg'

$pHasher = new ImageComparator();
$similarity = $imageComparator->detect($image1, $image2); //default hashing without rotation

echo $similarity; //95.33
```

With `compareArray()` and `detectArray()` methods you can compare the source image to any number of images you want.
The behaviour is the same as in `compare()` and `detect()` methods.

```php
use SapientPro\ImageComparator\ImageComparator;

$image1 = 'https://raw.githubusercontent.com/sapientpro/phasher/feature/phasher-implementation/tests/images/forest1.jpg';
$image2 = 'https://raw.githubusercontent.com/sapientpro/phasher/feature/phasher-implementation/tests/images/forest1-copyrighted.jpg'
$image3 = 'https://raw.githubusercontent.com/sapientpro/phasher/feature/phasher-implementation/tests/images/forest.jpg'

$imageComparator = new ImageComparator();
$similarity = $imageComparator->compareArray(
    $image1,
    [
        'forest' => $image2,
        'anotherForest' => $image3
    ]
); // returns ['forest' => 95.33, 'anotherForest' => 75.22]
```

If needed, you can create a square image resource from another image
and pass it to `compare()`, `compareArray()`, `detect()`, `detectArray` and `hashImage()` methods:

```php
use SapientPro\ImageComparator\ImageComparator;

$image1 = 'https://raw.githubusercontent.com/sapientpro/phasher/feature/phasher-implementation/tests/images/forest1.jpg';
$image2 = 'https://raw.githubusercontent.com/sapientpro/phasher/feature/phasher-implementation/tests/images/forest1-copyrighted.jpg'

$imageComparator = new ImageComparator();

$squareImage1 = $imageComparator->squareImage($image1);
$squareImage2 = $imageComparator->squareImage($image2);

$similarity = $imageComparator->compare($squareImage1, $squareImage2);

echo $similarity //96.43;
```

If needed, you can convert the resulting array from `hashImage()` to a binary string and pass it to `compareHashStrings()` method:

```php
use SapientPro\ImageComparator\ImageComparator;

$image1 = 'https://raw.githubusercontent.com/sapientpro/phasher/feature/phasher-implementation/tests/images/forest1.jpg';
$image2 = 'https://raw.githubusercontent.com/sapientpro/phasher/feature/phasher-implementation/tests/images/forest1-copyrighted.jpg'

$imageComparator = new ImageComparator();

$hash1 = $imageComparator->hashImage($image1);
$hash2 = $imageComparator->hashImage($image2);

$hashString1 = $imageComparator->convertHashToBinaryString($hash1);
$hashString2 = $imageComparator->convertHashToBinaryString($hash2);

$similarity = $imageComparator->compareHashStrings($hashString1, $hashString2);

echo $similarity //96.43;
```


## Available methods:
#### hashImage()

Create a perceptual hash from an image. Returns an array of individual bits containing 0 and 1

```php
$imageComparator->hashImage('your-images/your-image.jpg');
```

The method accepts the following arguments:

* `$image` - image path or an instance of GdImage created by _gd_ functions,
  e.g. imagecreatefromstring();
* `$rotation` - image rotation angle, accepted values are: 0 (default), 90, 180 and 270;
* `$size` - the size of the thumbnail created from the original image -
the hash size will be the square of this (so a value of 8 will build a hash out of 8x8 image, of 64 bits.)
* `$hashType` - the method of hashing - difference hashing or average hashing.
Accepted values are _"aHash"_ and _"dHash"_ for average and difference hashing respectively

```php
$imageComparator->hashImage(image: 'your-images/your-image.jpg', rotation: 90, size: 16, hashType: 'dHash');
```

#### fastHashImage()
Experimental hashing method. Heavily modified from a bicubic resampling function by an unknown author here:
http://php.net/manual/en/function.imagecopyresampled.php#78049

This will scale down, desaturate and hash an image entirely in memory 
without the intermediate steps of altering the image resource and
re-reading pixel data, and return a perceptual hash for that image.

Doesn't support rotation

Accepts image path or an instance of GdImage instance.
Optionally accepts `$size` argument which determines the size of the hash

Returns the same array as `hashImage()`

```php
$imageComparator->fastHashImage(image: 'your-images/your-image.jpg', size: 16);
```

#### compare()
Compare two images and return the percentage of their similarity

Accepts the following arguments:

* `$sourceImage` - image path or an instance of GdImage of the image to compare to;
* `$comparedImage` - image path or an instance of GdImage of the compared image;
* `$rotation` - rotation angle of the compared image, accepted values are: 0 (default), 90, 180 and 270;
* `$precision` - number of decimal points of the resulting percentage

```php
$imageComparator->compare(
    sourceImage: 'your-images/your-image1.jpg',
    comparedImage: 'your-images/your-image2.jpg',
    rotation: 0,
    precision: 2
    ) //86.32
```


#### compareArray()
Same as `compare()`, but allows to compare source image to an array of images.

Returns an array percentages of the similarity of each image

Accepts the following arguments:

* `$sourceImage` - image path or an instance of GdImage of the image to compare to;
* `$images` - array of images;
* `$rotation` - rotation angle of the compared image, accepted values are: 0 (default), 90, 180 and 270;
* `$precision` - number of decimal points of the resulting percentage

```php
$imageComparator->compareArray(
    sourceImage: 'your-images/your-image1.jpg',
    images: ['image1' => 'your-images/your-image2.jpg', 'image2' => 'your-images/your-image2.jpg'],
    rotation: 0,
    precision: 2
    ) // ['image1' => 86.32, 'image2' => 21.33]
```

#### detect()
Compare two images through rotations of the compared image by 0, 90, 180 and 270 degrees
and return the highest percentage of their similarity

Accepts the following arguments:

* `$sourceImage` - image path or an instance of GdImage of the image to compare to;
* `$comparedImage` - image path or an instance of GdImage of the compared image;
* `$precision` - number of decimal points of the resulting percentage

```php
$imageComparator->detect(
    sourceImage: 'your-images/your-image1.jpg',
    comparedImage: 'your-images/your-image2.jpg',
    precision: 2
    ) //86.32
```

#### detectArray()
Same as `detect()`, but allows to compare source image to an array of images.
Returns the array of highest percentages of the similarity of each image

Accepts the following arguments:

* `$sourceImage` - image path or an instance of GdImage of the image to compare to;
* `$images` - array of images;
* `$precision` - number of decimal points of the resulting percentage

```php
$imageComparator->detect(
    sourceImage: 'your-images/your-image1.jpg',
    comparedImage: ['image1' => 'your-images/your-image2.jpg', 'image2' => 'your-images/your-image2.jpg'],
    precision: 2
    ) // ['image1' => 86.32, 'image2' => 21.33]
```

#### squareImage()
Create a square image resource from another rectangular image:

```php
getimagesizefromstring('your-images/your-image1.jpg'); // width: 500, height: 250

$squareImage = $imageComparator->squareImage('your-images/your-image1.jpg');

imagesx($squareImage); // width: 500
imagesy($squareImage); // height: 500
```

#### convertHashToBinaryString()

Convert the resulting array from `hashImage` to a binary string:

```php
$hash = [0,1,1,1,0,1,0,0,1,1,1,1,0,0,0,0];

$binaryString = $imageComparator->convertHashToBinaryString($hash);

echo $binaryString // "0,1,1,1,0,1,0,0,1,1,1,1,0,0,0,0"
```

#### compareHashStrings()

Compare hash strings. Rotation is not available:

```php
$hash1 = [0,1,1,1,0,1,0,0,1,1,1,1,0,0,0,0];
$binaryString1 = $imageComparator->convertHashToBinaryString($hash1);

$hash2 = [0,1,1,1,0,1,0,0,1,1,1,0,0,0,0,0];
$binaryString2 = $imageComparator->convertHashToBinaryString($hash2);

$similarity = $imageComparator->compareHashStrings($binaryString1, $binaryString2);

echo $similarity //93.8
```