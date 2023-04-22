# Image Comparator

<!-- TOC -->
* [Image Comparator](#image-comparator)
  * [Installation](#installation)
    * [Prerequisites](#prerequisites)
  * [Usage](#usage)
  * [Available methods:](#available-methods)
      * [hashImage()](#hashimage)
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

`ImageComparator` is the core class of the library:

```php
use SapientPro\ImageComparator\ImageComparator;

$imageComparator = new ImageComparator()
```

After creating an instance you can use one of the methods available:

```php
$imageComparator->compare('your-images/your-image1.jpg', 'your-images/your-image12.jpg');
```

If the image path can't be resolved, `ImageResourceException` will be thrown:

```php
$imageComparator->hashImage('your-images/non-existent-image.jpg'); // SapientPro\ImageComparator\ImageResourceException: Could not create an image resource from file
```

Example usage:

We have two images:

![Equals1](https://github.com/sapientpro/image-comparator/blob/feature/phasher-implementation/tests/images/ebay-image.png?raw=true)
![Equals2](https://github.com/sapientpro/image-comparator/blob/feature/phasher-implementation/tests/images/amazon-image.png?raw=true)

Now, let's compare them:

```php
use SapientPro\ImageComparator\ImageComparator;

$image1 = 'https://github.com/sapientpro/image-comparator/blob/feature/phasher-implementation/tests/images/ebay-image.png?raw=true';
$image2 = 'https://github.com/sapientpro/image-comparator/blob/feature/phasher-implementation/tests/images/amazon-image.png?raw=true'

$imageComparator = new ImageComparator();
$similarity = $imageComparator->compare($image1, $image2); //default hashing without rotation

echo $similarity; //87.5
```
The higher the result, the higher the similarity of images.

Let's compare different images:

![Equals1](https://github.com/sapientpro/image-comparator/blob/feature/phasher-implementation/tests/images/ebay-image2.png?raw=true)
![Equals2](https://github.com/sapientpro/image-comparator/blob/feature/phasher-implementation/tests/images/amazon-image2.png?raw=true)

```php
use SapientPro\ImageComparator\ImageComparator;

$image1 = 'https://github.com/sapientpro/image-comparator/blob/feature/phasher-implementation/tests/images/ebay-image2.png?raw=true';
$image2 = 'https://github.com/sapientpro/image-comparator/blob/feature/phasher-implementation/tests/images/amazon-image2.png?raw=true'

$imageComparator = new ImageComparator();
$similarity = $imageComparator->compare($image1, $image2); //default hashing without rotation

echo $similarity; //54.7
```

Rotation angle can be passed if compared image is rotated.
You must pass \SapientPro\ImageComparator\Enum\ImageRotationAngle enum with one of the following values:
`D0` = 0 degress, `D90` = 90 degrees, `D180` = 180 degrees, `D270` = 270 degrees

```php
use SapientPro\ImageComparator\Enum\ImageRotationAngle;

$similarity = $imageComparator->compare($image1, $image2, ImageRotationAngle::D180); //compared image will be considered rotated by 180 degrees

echo $similarity; //95.3
```

You can also use `detect()` method which will rotate the compared image and return the highest percentage of similarity:

```php
use SapientPro\ImageComparator\ImageComparator;

$image1 = 'https://raw.githubusercontent.com/sapientpro/phasher/feature/phasher-implementation/tests/images/forest1.jpg';
$image2 = 'https://raw.githubusercontent.com/sapientpro/phasher/feature/phasher-implementation/tests/images/forest1-copyrighted.jpg'

$imageComparator = new ImageComparator();
$similarity = $imageComparator->detect($image1, $image2);

echo $similarity; //95.3
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

By default, images are hashed using the average hashing algorithm,
which is implemented in `SapientPro\ImageComparator\Strategy\AverageHashStrategy`.
This strategy is initialized in the constructor of `ImageComparator`.

It is also possible to use difference hashing algorithm, implemented in SapientPro\ImageComparator\Strategy\DifferenceHashStrategy.
To use it, you need to call ImageComparator's `setHashingStrategy()` method and pass the instance of the strategy:

```php
use SapientPro\ImageComparator\Strategy\DifferenceHashStrategy;

$imageComparator->setHashingStrategy(new DifferenceHashStrategy());
$imageComparator->hashImage('image1.jpg');
```

If the strategy is set by `setHashingStrategy()`, it will be used under the hood in other comparison methods:

```php
use SapientPro\ImageComparator\Strategy\DifferenceHashStrategy;

$imageComparator->setHashingStrategy(new DifferenceHashStrategy());
$imageComparator->compare('image1.jpg', 'image2.jpg'); // images will be hashed using difference hash algorithm and then compared
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
* `$rotation` - image rotation angle enum instance, e.g. ImageRotationAngle::D90;
* `$size` - the size of the thumbnail created from the original image -
the hash size will be the square of this (so a value of 8 will build a hash out of 8x8 image, of 64 bits.)

```php
$imageComparator->hashImage(image: 'your-images/your-image.jpg', rotation: ImageRotationAngle::D90, size: 16);
```

#### compare()
Compare two images and return the percentage of their similarity

Accepts the following arguments:

* `$sourceImage` - image path or an instance of GdImage of the image to compare to;
* `$comparedImage` - image path or an instance of GdImage of the compared image;
* `$rotation` - image rotation angle enum instance, e.g. ImageRotationAngle::D90;
* `$precision` - number of decimal points of the resulting percentage

```php
$imageComparator->compare(
    sourceImage: 'your-images/your-image1.jpg',
    comparedImage: 'your-images/your-image2.jpg',
    rotation: ImageRotationAngle::D90,
    precision: 2
    ) //86.32
```


#### compareArray()
Same as `compare()`, but allows to compare source image to an array of images.

Returns an array percentages of the similarity of each image

Accepts the following arguments:

* `$sourceImage` - image path or an instance of GdImage of the image to compare to;
* `$images` - array of images;
* `$rotation` - image rotation angle enum instance, e.g. ImageRotationAngle::D90;
* `$precision` - number of decimal points of the resulting percentage

```php
$imageComparator->compareArray(
    sourceImage: 'your-images/your-image1.jpg',
    images: ['image1' => 'your-images/your-image2.jpg', 'image2' => 'your-images/your-image2.jpg'],
    rotation: ImageRotationAngle::D90,
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

echo $binaryString // "0111010011110000"
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