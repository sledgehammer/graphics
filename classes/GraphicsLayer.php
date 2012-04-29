<?php
namespace SledgeHammer;
/**
 * A single GraphicsLayer
 *
 * @property int $width
 * @property int $height
 * @param float $aspectRatio (readonly)
 */
class GraphicsLayer extends Object {

	/**
	 * @var resource GD
	 */
	protected $gd;

	function __construct($gd) {
		$this->gd = $gd;
	}

	function __destruct() {
		if ($this->gd !== null) {
			imagedestroy($this->gd);
		}
	}

	/**
	 * Returns a new Image in the given size.
	 *
	 * @param int $width
	 * @param int $height
	 * @return Image
	 */
	function resized($width, $height) {
		$gd = $this->rasterizeTrueColor();
		$resized = $this->createCanvas($width, $height);
		imagecopyresampled($resized, $gd, 0, 0, 0, 0, $width, $height, imagesx($gd), imagesy($gd));
		return new Image(new GraphicsLayer($resized));
	}

	/**
	 * Return a new Image with the cropped contents. Resizes the canvas, but not the contents.
	 *
	 * @param int $width  The new width
	 * @param int $height  The new height
	 * @param int $offsetLeft  Offset left (null: centered)
	 * @param int $offsetTop  Offset top (null: centered)
	 */
	function cropped($width, $height, $offsetLeft = null, $offsetTop = null) {
		$gd = $this->rasterizeTrueColor();
		$top = 0;
		$left = 0;
		$sourceWidth = imagesx($gd);
		$sourceHeight = imagesy($gd);

		if ($offsetLeft === null || $offsetTop === null) {
			if ($offsetLeft === null) { // horizontal-align center?
				$offsetLeft = floor(($sourceWidth - $width) / 2.0);
			}
			if ($offsetTop === null) { // vertical-align center?
				$offsetTop = floor(($sourceHeight - $height) / 2.0);
			}
		}
		$cropped = $this->createCanvas($width, $height);
		if ($offsetTop < 0) {
			$top = -1 * $offsetTop;
			$offsetTop = 0;
		}
		if ($offsetLeft < 0) {
			$left = -1 * $offsetLeft;
			$offsetLeft = 0;
		}
		imagecopy($cropped, $gd, $left, $top , $offsetLeft, $offsetTop, $sourceWidth, $sourceHeight);
		return new Image(new GraphicsLayer($cropped));
	}

	/**
	 * Return a new Image in te given rotation
	 *
	 * @param float $angle
	 * @param string $bgcolor
	 * @return Image
	 */
	function rotated($angle, $bgcolor = 'rgba(255,255,255,0)') {
		$gd = $this->rasterizeTrueColor();
		$rotated = imagerotate($gd, $angle, $this->colorIndex($bgcolor, $gd));
		return new Image(new GraphicsLayer($rotated));
	}

	function __get($property) {
		switch ($property) {

			case 'width':
			case 'height':
			case 'aspectRatio':
				$method = 'get'.ucfirst($property);
				return $this->$method();
		}
		return parent::__get($property);
	}

	/**
	 * Render the layer to the gd resource container
	 *
	 * @param resource $gd
	 */
	protected function rasterizeTo($gd, $x, $y) {
		imagecopy($gd, $this->rasterizeTrueColor(), $x, $y, 0, 0, $this->width, $this->height);
	}

	/**
	 * Rasterize the layer to a truecolor(32bit) GD resource.
	 *
	 * @return resource gd
	 */
	protected function rasterizeTrueColor() {
		$gd = $this->rasterize();
		if (imageistruecolor($gd)) {
			return $gd;
		}
		$height = imagex($gd);
		$width = imagex($gd);
		$this->gd = imagecreatetruecolor($width, $height);
		imagecopy($this->gd, $gd, 0, 0, 0, 0, $width, $height);
		imagedestroy($gd);
		$this->gd = $gd;
		return $gd;
	}
	/**
	 * Rasterize the layer to an GD resource.
	 * Updates and returns the internal gd resource
	 *
	 * @return resource gd
	 */
	protected function rasterize() {
		if ($this->gd === null) {
			notice(get_class($this).'->rasterize() failed');
			// return a nixel (transparent pixel)
			$this->gd = imagecreatetruecolor(1, 1);
			imagecolortransparent($this->gd, 0);
		}
		return $this->gd;
	}

	protected function getWidth() {
		return imagesx($this->rasterize());
	}

	protected function getHeight() {
		return imagesy($this->rasterize());
	}

	protected function getAspectRatio() {
		$gd = $this->rasterize();
		return imagesx($gd) / imagesy($gd);
	}

	/**
	 * Create a transparent gd resource with full (white) alphachannel
	 *
	 * @param int $width
	 * @param int $height
	 * @return type
	 */
	protected function createCanvas($width, $height, $color = 'rgba(255,255,255,0)') {
		$gd = imagecreatetruecolor($width, $height);
		imagealphablending($gd, false);
		imagefilledrectangle($gd, 0, 0, $width, $height, $this->colorIndex($color, $gd));
		imagealphablending($gd, true);
		imagesavealpha($gd, true);
		return $gd;
	}

	/**
	 * Resolve/Allocate palete index for the given $color
	 *
	 * @param string $color Allowed syntax:
	 * 	'red'
	 *  '#f00'
	 *  '#ff0000'
	 *  'rgb(255, 0, 0)'
	 *  'rgba(255, 0, 0, 0.5)'
	 *
	 * @param $gd (optional) GD resource
	 * @return int
	 */
	protected function colorIndex($color, $gd = null) {
		if ($gd === null) {
			$gd = $this->gd;
		}
		$color = strtolower($color);
		$colorNames = array(
			'black' => '000000',
			'red' => 'ff0000',
			'lime' => '00ff00',
			'blue' => '0000ff',
			'yellow' => 'ffff00',
			'aqua' => '00ffff',
			'fuchsia' => 'ff00ff',
			'white' => 'ffffff',
			'silver' => 'c0c0c0',
			'gray' => '808080',
			'purple' => '800080',
			'maroon' => '800000',
			'green' => '008000',
			'olive' => '808000',
			'navy' => '000080',
			'teal' => '008080',
		);
		if (isset($colorNames[$color])) {
			$color = '#'.$colorNames[$color];
		}
		if (preg_match('/^#([0-9abcdef]{6})$/', $color, $match)) {
			// #ffffff notatie
			$red = hexdec(substr($match[1], 0, 2));
			$green = hexdec(substr($match[1], 2, 2));
			$blue = hexdec(substr($match[1], 4, 2));
		} elseif (preg_match('/^#([0-9abcdef]{3})$/', $color, $match)) {
			// #fff notatie?
			$red = hexdec(substr($match[1], 0, 1)) * 16;
			$green = hexdec(substr($match[1], 1, 1)) * 16;
			$blue = hexdec(substr($match[1], 2, 1)) * 16;
		} elseif (preg_match('/^\s{0,}rgb\s{0,}\(\s{0,}([0-9]+)\s{0,},\s{0,}([0-9]+)\s{0,},\s{0,}([0-9]+)\s{0,}\)\s{0,}$/', $color, $match)) {
			// rgb(255, 255, 255) notation
			$red = $match[1];
			$green = $match[2];
			$blue = $match[3];
		} elseif (preg_match('/^\s{0,}rgba\s{0,}\(\s{0,}([0-9]+)\s{0,},\s{0,}([0-9]+)\s{0,},\s{0,}([0-9]+)\s{0,},\s{0,}(0|1|[01]{0,1}\.[0-9]+)\s{0,}\)\s{0,}$/', $color, $match)) {
			// rgba(255, 255, 255, 0.5) notation
			$red = $match[1];
			$green = $match[2];
			$blue = $match[3];
			$alpha = ceil((1 - $match[4]) * 127);
			$pallete = imagecolorexactalpha($gd, $red, $green, $blue, $alpha); // Resolve color
			if ($pallete !== -1) {
				return $pallete;
			}
			return imagecolorallocatealpha($gd, $red, $green, $blue, $alpha); // Allocate color
		} else {
			notice('Unsupported color notation: "'.$color.'"');
			return -1;
		}
		$pallete = imagecolorexact($gd, $red, $green, $blue);  // Resolve color
		if ($pallete !== -1) {
			return $pallete;
		}
		return imagecolorallocate($gd, $red, $green, $blue); // Allocate color
	}

}

?>
