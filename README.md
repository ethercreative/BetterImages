# BetterImages
A better way of generating images code side, for WordPress.

## Usage
### Basic Usage
```php
$image; // Set to an image array, id, or local url

echo new bi($image)->resize()->width(500)->blur(10)->go();
```

*You don't have to define the resize method (resize, constrain), or the width or height if you only want to pass the image through some filters*

### Functions

#### bi(mixed $image)
```php
new bi($image);
```

The **first function** you will use when creating a BetterImage. ```$image``` can be set to a WP image array, ID, or a local url (remote files are currently not supported).


#### resize()
```php
bi()->resize();
```

Sets the method to resize (the default)

* Setting both the width and height will stretch the image (if the aspect ratio is different)
* You can upscale the image
* Leaving either (not both) the width or height values unset, or 'auto' will scale the image keeping the original aspect ratio


#### constrain()
```php
bi()->constrain();
```

Sets the method to constrain

* The image will resized and cropped to fit within the defined width and height


#### width(int $width)
```php
bi()->width(500);
```

Sets the width of the new image


#### height(int $height)
```php
bi()->height(500);
```

Sets the height of the new image


#### invert()
```php
bi()->invert();
```

Invert the colours of the image


#### grayscale()
```php
bi()->grayscale();
```

Make the image grayscale (black and white)


#### brightness(int $level)
```php
bi()->brightness(100);
```

Set the brightness of the image

* Minimum Brightness (darkest): -255
* No Change: 0
* Maximum Brightness (lightest): 255
* ```int $level``` The level of brightness (min: -255, max: 255)


#### contrast(int $level)
```php
bi()->contrast(100);
```

Set the contrast level of the image *(Note the direction, it is opposite to brightness)*

* Minimum Contrast: 100
* No Change: 0
* Maximum Contrast: -100
* ```int $level``` The level of contrast (min: 100, max: -100)


#### colorize(int $red, int $green, int $blue, int $alpha)
```php
bi()->colorize(255, 0, 0, 0);
```

Colorizes the image

* Red, Green, & Blue values range from -255 to 255, where 0 is no change
* Alpha values range from 0 (opaque) to 127 (transparent)
* The alpha value effects the strength of the colorize effect, not the image itself
* ```int $red``` The red value (min: -255, max: 255)
* ```int $green``` The green value (min: -255, max: 255)
* ```int $blue``` The blue value (min: -255, max: 255)
* ```int $alpha``` The alpha value (min/opaque: 0, max/transparent: 127)


#### edgedetect()
```php
bi()->edgedetect();
```

Highlights the edges of objects in the image on a gray background


#### emboss()
```php
bi()->emboss();
```

Creates an emboss effect on the edges of objects in the image, on a gray background


#### blur(int $amount [, string $type = 'gaussian'])
```php
bi()->blur(10, 'selective');
```

Blurs the image

* Defaults to gaussian blur
* ```gaussian``` is a traditional blur
* ```selective``` is much smoother
* ```int $amount``` The number of times the image is passed through the blur filter
* ```string $type``` The type of blur (gaussian, selective)


#### sharpen()
```php
bi()->sharpen();
```

Creates a sharper image via mean removal


#### smooth(float $amount)
```php
bi()->smooth(10);
```

Smooths the image

* Applies a 9-cell convolution matrix where center pixel has the weight $amount and others weight of 1.0.
* The result is normalized by dividing the sum with $amount + 8.0 (sum of the matrix).
* Any float is accepted, large values (in practice: 2048 or more) = no change
* ```float $amount``` The amount of smoothing


#### pixelate(int $amount [, bool $use_advanced = false ])
```php
bi()->pixelate(25, true);
```

Pixelate the image

* ```int $pixel_size``` The block size in px
* ```bool $use_advanced``` Whether or not to use the advanced pixelation effect (default: false)


#### go([ bool $force_image_re_save = false ])
```php
bi()->go();
```

The *last function* you run in the BetterImages function chain. It triggers all the image manipulations and returns the new image URL.

* ```bool $force_image_re_save``` Re-save the image, even if it already exists (cache breaking)