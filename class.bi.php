<?php

class bi
{

	protected $original_image;
	protected $original_url = '';
	protected $original_name = '';
	protected $original_dir = '';
	protected $original_filetype = '';
	protected $original_width = 0;
	protected $original_height = 0;
	protected $original_aspect_ratio = 0;

	protected $new_image;
	protected $new_url = '';
	protected $new_name = '';
	protected $new_width = 'auto';
	protected $new_height = 'auto';
	protected $new_aspect_ratio = 0;

	protected $method = 'resize';
	protected $quality = -1;
	protected $filters = [];

	private $bi_dir = '/.bi/';
	private $error = '';


	/**
	 * Setup the image for manipulation
	 *
	 * @param mixed $image The image (can be WP image array or ID, or absolute URL)
	 */
	function __construct($image)
	{

		// Set error stuff    WP SPECIFIC
		$this->error = [
			'max_width' => 1500,
			'max_height' => 1500,
			'too_large' => plugins_url('imgs/too_large.jpg', __FILE__)
		];

		// Check image is not empty
		if (empty($image)) return '';

		// Image Array, URL, or ID?
		if (gettype($image) === 'array') {
			// WP SPECIFIC
			$this->original_url = $image['url'];
		} elseif (gettype($image) === 'string') {
			$this->original_url = $image;
		} elseif (gettype($image) === 'integer') {
			// WP SPECIFIC
			$obj = wp_get_attachment_image_src($image, 'full');
			if (!$obj) return ''; // If returned obj is false
			$this->original_url = $obj[0];
		} else {
			throw new InvalidArgumentException('WordPress image type not recognized. Please enter a valid image array, url, or ID.');
		}

		// Get Filename
		$this->original_name = $this->_upload_url_to_path();

		// Get Directory
		$this->original_dir = $this->_upload_url_to_path(true);

		// Check original still exists
		if (!file_exists($this->original_dir . $this->original_name)) return '';

		return $this;

	}

	public function method($method)
	{
		$this->method = $method;

		return $this;
	}

	public function width($width)
	{
		$this->new_width = $width;

		return $this;
	}

	public function height($height)
	{
		$this->new_height = $height;

		return $this;
	}

	public function quality($quality)
	{
		$this->quality = $quality;

		return $this;
	}

	public function filter($filter_name, $filter_args1 = null, $filter_args2 = null, $filter_args3 = null, $filter_args4 = null)
	{
		$this->filters[] = [
			$filter_name,
			[$filter_args1, $filter_args2, $filter_args3, $filter_args4]
		];

		return $this;
	}

	/**
	 * Perform all the image manipulations
	 *
	 * @param bool $force_image_re_save Re-save the image, even if it already exists
	 * @return string The new image URL (via the save function)
	 */
	public function go($force_image_re_save = false)
	{

		if ($this->original_url === '' || $this->original_name === '') return '';

		// Check if method is valid
		if (!in_array($this->method, ['constrain', 'resize']))
			throw new InvalidArgumentException('Please enter a valid method, or leave blank for the default (constrain).');

		// Check if either width or height have been set to auto, without the other being set to a fixed value
		if (($this->new_width === 'auto' && ($this->new_height !== 'auto' && $this->new_height <= 0)) ||
			($this->new_height === 'auto' && ($this->new_width !== 'auto' && $this->new_width <= 0))
		) {
			throw new InvalidArgumentException("When using 'auto' for either width or height, the other value must be set to a number greater than 0.");
		}

		// Create New Name
		$this->new_name = $this->_create_new_filename();

		// Create the new image url
		$this->new_url = $this->_upload_file_to_url();

		// Check if file already exists (and that we're not forcing the re-save)
		if (file_exists($this->original_dir . $this->bi_dir . $this->new_name) && !$force_image_re_save) {
			return $this->new_url;
		}

		// Create the image resource for manipulation
		$this->original_image = $this->_create_image_resource();

		// Set the original image sizes
		$this->original_width = imagesx($this->original_image);
		$this->original_height = imagesy($this->original_image);

		// If width & height are both auto (i.e. not set)
		$areWeResizing = true;
		if ($this->new_width === 'auto' && $this->new_height === 'auto') {
			$areWeResizing = false;
			$this->new_width = $this->original_width;
			$this->new_height = $this->original_height;
		}

		// If the new image sizes haven't been set, set them to the originals
		if ($this->original_width <= 0) $this->new_width = $this->original_width;
		if ($this->original_height <= 0) $this->new_height = $this->original_height;

		// Original Aspect Ratio
		$this->original_aspect_ratio = $this->original_width / $this->original_height;

		// Calculate 'auto' measurements
		if ($this->new_width === 'auto') $this->new_width = $this->new_height * $this->original_aspect_ratio;
		if ($this->new_height === 'auto') $this->new_height = $this->new_width / $this->original_aspect_ratio;

		// New Aspect Ratio
		$this->new_aspect_ratio = $this->new_width / $this->new_height;

		try {
			// Crop / Resize Image
			if ($areWeResizing) {
				switch ($this->method) {
					case 'constrain':
						$this->_constrain_image();
						break;
					case 'resize':
						$this->_resize_image();
						break;
				}
			} else {
				$this->new_image = $this->original_image;
			}

			// Apply Filters
			if ($this->filters !== []) {
				$this->_apply_filters();
			}
		} catch (Exception $e) {
			return $e->getMessage();
		}

		// Save the new image and return it's URL
		return $this->_save_image();
	}

	/**
	 * Convert Image URL to absolute path and filename
	 *
	 * @param bool $returnPath Return the directory, not the filename
	 * @return string
	 */
	private function _upload_url_to_path($returnPath = false)
	{

		// TODO: Remove WP specific code
		$wp_base = getcwd();

		$upload_dir = wp_upload_dir();
		$upload_dir = $upload_dir['basedir'];

		$match = str_replace($wp_base, '', $upload_dir);

		$url_arr = explode($match, $this->original_url);

		$path_info = pathinfo($url_arr[1]);

		if ($returnPath) {
			return $upload_dir . $path_info['dirname'] . '/';
		} else {
			return $path_info['basename'];
		}

	}

	/**
	 * Create the url for the new image
	 *
	 * @return string
	 */
	private function _upload_file_to_url()
	{

		// TODO: Remove WP specific code
		$wp_base = getcwd();

		$upload_dir = wp_upload_dir();
		$upload_dir = $upload_dir['basedir'];

		$match = str_replace($wp_base, '', $upload_dir);

		$url_arr = explode($match, $this->original_url);

		$path_info = pathinfo($url_arr[1]);

		return $url_arr[0] . $match . $path_info['dirname'] . $this->bi_dir . $this->new_name;

	}

	/**
	 * Create the filename for the new image
	 *
	 * @return string
	 */
	private function _create_new_filename()
	{
		$p = pathinfo($this->original_name);
		$extension = $p['extension'];

		$name = '';

		if ($this->new_width > 0 || $this->new_width === 'auto') $name .= '_' . $this->new_width;
		if ($this->new_height > 0 || $this->new_height === 'auto') $name .= '_' . $this->new_height;

		foreach ($this->filters as $filter) {
			$name .= '_' . $filter[0];
		}

		$name .= '_' . $this->quality;

		$name .= '_' . $this->method;

		return str_replace('.' . $extension, '', $this->original_name) . $name . '.' . $extension;
	}

	/**
	 * Create the image resource of the image being modified
	 *
	 * @return resource
	 */
	private function _create_image_resource()
	{

		$filepath = $this->original_dir . $this->original_name;

		$p = pathinfo($filepath);
		$this->original_filetype = ($p['extension'] == 'jpeg' ? 'jpg' : $p['extension']);
		switch ($p['extension']) {
			case 'jpeg':
			case 'jpg':
				return imagecreatefromjpeg($filepath);
				break;

			case 'png':
				return imagecreatefrompng($filepath);
				break;

			case 'gif':
				return imagecreatefromgif($filepath);
				break;

			default:
				throw new InvalidArgumentException('File "' . $filepath . '" is not valid jpg, png or gif image.');
				break;
		}
	}

	/**
	 * Resize the image while cropping it to a specified width / height
	 */
	private function _constrain_image()
	{

		$ow = $this->original_width;
		$oh = $this->original_height;
		$oar = $this->original_aspect_ratio;

		$w = $this->new_width;
		$h = $this->new_height;
		$nar = $this->new_aspect_ratio;

		$image = $this->original_image;

		if ($oar > $nar) { // Source image is wider
			$tw = $h * $oar;
			$th = $h;
		} else { // Source image is similar or taller
			$tw = $w;
			$th = $w / $oar;
		}

		// Resize in temp GD image
		$tmp_img = $this->_imagecreatetruecolor($tw, $th);

		$this->_set_alpha($tmp_img);

		imagecopyresampled($tmp_img, $image, 0, 0, 0, 0, $tw, $th, $ow, $oh);

		// Crop from temp to desired
		$x0 = ($tw - $w) / 2;
		$y0 = ($th - $h) / 2;

		$desired_img = $this->_imagecreatetruecolor($w, $h);

		$this->_set_alpha($desired_img);

		imagecopy($desired_img, $tmp_img, 0, 0, $x0, $y0, $w, $h);

		$this->new_image = $desired_img;
	}

	/**
	 * Resize the image
	 */
	private function _resize_image()
	{

		$ow = $this->original_width;
		$oh = $this->original_height;
		$oar = $this->original_aspect_ratio;

		$w = $this->new_width;
		$h = $this->new_height;
		$nar = $this->new_aspect_ratio;

		$image = $this->original_image;

		// Force no upscaling or stretching
//		if ($ow <= $w && $oh <= $h) {
//			$tw = $ow;
//			$th = $oh;
//		} elseif ($nar > $oar) {
//			$tw = $h * $oar;
//			$th = $h;
//		} else {
//			$tw = $w;
//			$th = $w / $oar;
//		}

		$tw = $w;
		$th = $h;

//		$tmp_img = imagecreatetruecolor($tw, $th);
		$tmp_img = $this->_imagecreatetruecolor($tw, $th);

		$this->_set_alpha($tmp_img);

		imagecopyresampled($tmp_img, $image, 0, 0, 0, 0, $tw, $th, $ow, $oh);

		$this->new_image = $tmp_img;
	}

	private function _imagecreatetruecolor($w, $h)
	{
		if ($w > $this->error['max_width'] && $h > $this->error['max_height']) throw new BIException($this->error['too_large']);

		return imagecreatetruecolor($w, $h);
	}

	private function _set_alpha($image)
	{

		switch ($this->original_filetype) {
			case 'jpg':
				// No transparency, do nothing
				break;
			case 'png':
				imagealphablending($image, false);
				imagesavealpha($image, true);
				break;
			case 'gif':
				$transparent = imagecolorallocatealpha($image, 0, 0, 0, 127); // Black
				imagefill($image, 0, 0, $transparent);
				imagecolortransparent($image, $transparent);
				imagealphablending($image, true);
				imagesavealpha($image, true);
				break;
		}

	}

	/**
	 * Apply the filters to the image
	 */
	private function _apply_filters()
	{
		if ($this->new_width > $this->error['max_width'] && $this->new_height > $this->error['max_height']) throw new BIException($this->error['too_large']);

		/**
		 * FILTERS
		 * -------------------------------------------------------------------------------------
		 * Name          | Args                                           | filtertype
		 * -------------------------------------------------------------------------------------
		 * invert        | none                                           | IMG_FILTER_NEGATE
		 * grayscale     | none                                           | IMG_FILTER_GRAYSCALE
		 * brightness    | $arg1 = brightness level                       | IMG_FILTER_BRIGHTNESS
		 * contrast      | $arg1 = contrast level                         | IMG_FILTER_CONTRAST
		 * colorize      | $arg1 = red (0 - 255)                          | IMG_FILTER_COLORIZE
		 *               | $arg2 = green (0 - 255)                        |
		 *               | $arg3 = blue (0 - 255)                         |
		 *               | $arg4 = alpha (0 - 127)                        |
		 * edgedetect    | none                                           | IMG_FILTER_EDGEDETECT
		 * emboss        | none                                           | IMG_FILTER_EMBOSS
		 * blur          | $arg1 = blur amount                            | IMG_FILTER_GAUSSIAN_BLUR <-- $arg1 refers to number of times image is put through this filter
		 * selectiveblur | $arg1 = blur amount                            | IMG_FILTER_SELECTIVE_BLUR <-- $arg1 refers to number of times image is put through this filter
		 * sharpen       | none                                           | IMG_FILTER_MEAN_REMOVAL
		 * smooth        | $arg1 = smooth amount                          | IMG_FILTER_SMOOTH
		 * pixelate      | $arg1 = pixel size                             | IMG_FILTER_PIXELATE
		 *               | $arg2 = use advanced pixelation                |
		 */

		foreach ($this->filters as $filter) {

			$f = $filter[0];
			$a1 = $filter[1][0];
			$a2 = $filter[1][1];
			$a3 = $filter[1][2];
			$a4 = $filter[1][3];

			switch ($f) {
				case 'invert':
					imagefilter($this->new_image, IMG_FILTER_NEGATE);
					break;
				case 'grayscale':
					imagefilter($this->new_image, IMG_FILTER_GRAYSCALE);
					break;
				case 'brightness':
					if (!$a1) throw new InvalidArgumentException('You need to set the brightness level as the second filter argument');
					imagefilter($this->new_image, IMG_FILTER_BRIGHTNESS, $a1);
					break;
				case 'contrast':
					if (!$a1) throw new InvalidArgumentException('You need to set the contrast level as the second filter argument');
					imagefilter($this->new_image, IMG_FILTER_CONTRAST, $a1);
					break;
				case 'colorize':
					if (!isset($a1) || !isset($a2) || !isset($a3) || !isset($a4)) throw new InvalidArgumentException('You need to set the RGBA values as the second - fifth filter arguments');
					imagefilter($this->new_image, IMG_FILTER_COLORIZE, $a1, $a2, $a3, $a4);
					break;
				case 'edgedetect':
					imagefilter($this->new_image, IMG_FILTER_EDGEDETECT);
					break;
				case 'emboss':
					imagefilter($this->new_image, IMG_FILTER_EMBOSS);
					break;
				case 'gaussianblur':
					if (!$a1) throw new InvalidArgumentException('You need to set the blur amount as the second filter argument');
					$this->_custom_blur(IMG_FILTER_GAUSSIAN_BLUR, $a1);
					break;
				case 'selectiveblur':
					if (!$a1) throw new InvalidArgumentException('You need to set the blur amount as the second filter argument');
					$this->_custom_blur(IMG_FILTER_SELECTIVE_BLUR, $a1);
					break;
				case 'sharpen':
					imagefilter($this->new_image, IMG_FILTER_MEAN_REMOVAL);
					break;
				case 'smooth':
					if (!$a1) throw new InvalidArgumentException('You need to set the smooth amount as the second filter argument');
					imagefilter($this->new_image, IMG_FILTER_SMOOTH, $a1);
					break;
				case 'pixelate':
					if (!$a1) throw new InvalidArgumentException('You need to set the pixel size as the second filter argument');
					$a2 = $a2 ?: false;
					imagefilter($this->new_image, IMG_FILTER_PIXELATE, $a1, $a2);
					break;
			}

		}
	}

	/**
	 * Blur the image
	 *
	 * @param int $type The type of blur
	 * @param int $amount The number of times to apply the blur
	 */
	private function _custom_blur($type, $amount)
	{
		for ($i = 0; $i < $amount; $i++) {
			imagefilter($this->new_image, $type);
		}
	}

	/**
	 * Save the image and clear the cache
	 *
	 * @return string The new image URL
	 */
	private function _save_image()
	{

		// Check if .bi directory exists, else make it
		if (!file_exists($this->original_dir . $this->bi_dir)) {
			mkdir($this->original_dir . $this->bi_dir);
		}

		$image = $this->new_image;

		$url = $this->original_dir . $this->bi_dir . $this->new_name;

		$q = $this->quality;

		$qJpg = ($q == -1 ? 90 : $q);
		$reverse = [9, 8, 7, 6, 5, 4, 3, 2, 1, 0];
		$qPng = ($q == -1 ? -1 : $reverse[(int)round($q / 10, 0)]);
		$qPng = ($qPng > 9 ? 9 : $qPng);

		imageinterlace($image, 1);

		switch ($this->original_filetype) {
			case 'jpg':
				imagejpeg($image, $url, $qJpg);
				break;
			case 'png':
				imagepng($image, $url, $qPng);
				break;
			case 'gif':
				imagegif($image, $url);
				break;
		}

		imagedestroy($this->new_image);
		if (get_resource_type($this->original_image) === 'gd') imagedestroy($this->original_image);

		return $this->new_url;

	}

	//------------------------------------------------------------//
	// Shortcut Functions                                         //
	//------------------------------------------------------------//
	/**
	 * Sets the method to resize (the default)
	 * - Setting both the width and height will stretch the image (if the aspect ratio is different)
	 * - You can upscale the image
	 * - Leaving either (not both) the width or height values unset, or 'auto' will scale the image keeping the
	 *   original aspect ratio
	 *
	 * @return bi
	 */
	public function resize()
	{
		return $this->method('resize');
	}

	/**
	 * Sets the method to constrain
	 * - The image will resized and cropped to fit within the defined width and height
	 *
	 * @return bi
	 */
	public function constrain()
	{
		return $this->method('constrain');
	}

	/**
	 * Invert the colours of the image
	 *
	 * @return bi
	 */
	public function invert()
	{
		return $this->filter('invert');
	}

	/**
	 * Make the image grayscale (black and white)
	 *
	 * @return bi
	 */
	public function grayscale()
	{
		return $this->filter('grayscale');
	}

	/**
	 * Set the brightness of the image
	 * - Minimum Brightness (darkest): -255
	 * - No Change: 0
	 * - Maximum Brightness (lightest): 255
	 *
	 * @param int $level The level of brightness (min: -255, max: 255)
	 * @return bi
	 */
	public function brightness($level)
	{
		return $this->filter('brightness', $level);
	}

	/**
	 * Set the contrast level of the image
	 * - Minimum Contrast: 100
	 * - No Change: 0
	 * - Maximum Contrast: -100
	 *   (Note the direction, it is opposite to brightness)
	 *
	 * @param int $level The level of contrast (min: 100, max: -100)
	 * @return bi
	 */
	public function contrast($level)
	{
		return $this->filter('contrast', $level);
	}

	/**
	 * Colorizes the image
	 * - Red, Green, & Blue values range from -255 to 255, where 0 is no change
	 * - Alpha values range from 0 (opaque) to 127 (transparent)
	 * - The alpha value effects the strength of the colorize effect, not the image itself
	 *
	 * @param int $red The red value (min: -255, max: 255)
	 * @param int $green The green value (min: -255, max: 255)
	 * @param int $blue The blue value (min: -255, max: 255)
	 * @param int $alpha The alpha value (min/opaque: 0, max/transparent: 127)
	 * @return bi
	 */
	public function colorize($red, $green, $blue, $alpha)
	{
		return $this->filter('colorize', $red, $green, $blue, $alpha);
	}

	/**
	 * Highlights the edges of objects in the image on a gray background
	 *
	 * @return bi
	 */
	public function edgedetect()
	{
		return $this->filter('edgedetect');
	}

	/**
	 * Creates an emboss effect on the edges of objects in the image, on a gray background
	 *
	 * @return bi
	 */
	public function emboss()
	{
		return $this->filter('emboss');
	}

	/**
	 * Blurs the image
	 * - Defaults to gaussian blur
	 * - Gaussian is a traditional blur
	 * - Selective is much smoother
	 *
	 * @param int $amount The number of times the image is passed through the blur filter
	 * @param string $type The type of blur (gaussian, selective)
	 * @return bi
	 */
	public function blur($amount, $type = 'gaussian')
	{
		return $this->filter($type . 'blur', $amount);
	}

	/**
	 * Creates a sharper image via mean removal
	 *
	 * @return bi
	 */
	public function sharpen()
	{
		return $this->filter('sharpen');
	}

	/**
	 * Applies a 9-cell convolution matrix where center pixel has the weight $amount and others weight of 1.0.
	 * The result is normalized by dividing the sum with $amount + 8.0 (sum of the matrix).
	 * Any float is accepted, large value (in practice: 2048 or more) = no change
	 *
	 * @param float $amount The amount of smoothing
	 * @return bi
	 */
	public function smooth($amount)
	{
		return $this->filter('smooth', $amount);
	}

	/**
	 * Pixelate the image
	 *
	 * @param int $pixel_size The block size in px
	 * @param bool $use_advanced Whether or not to use the advanced pixelation effect (default: false)
	 * @return bi
	 */
	public function pixelate($pixel_size, $use_advanced = false)
	{
		return $this->filter('pixelate', $pixel_size, $use_advanced);
	}

}

class BIException extends Exception
{
}