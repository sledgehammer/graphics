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
	/**
	 * @var int Left position relative to the container
	 */
	public $x = 0;
	/**
	 * @var int Top position relative to the container
	 */
	public $y = 0;

	/**
	 * @var GraphicsContainer
	 */
	protected $container;

	function __construct($gd, $x = 0, $y = 0) {
		$this->gd = $gd;
		$this->x = $x;
		$this->y = $y;
	}

	function __destruct() {
		if ($this->gd !== null) {
			imagedestroy($this->gd);
		}
	}

	/**
	 * Rasterize the layer to an GD resource.
	 * Updates and returns the internal gd resource
	 *
	 * @return resource gd
	 */
	function rasterize() {
		if ($this->gd === null) {
			notice(get_class($this).'->rasterize() failed');
			// return a nixel (transparent pixel)
			$this->gd = imagecreatetruecolor(1, 1);
			imagecolortransparent($this->gd, 0);
		}
		return $this->gd;
	}

	/**
	 * Rasterize the layer to a truecolor(32bit) GD resource.
	 *
	 * @return resource gd
	 */
	function rasterizeTrueColor() {
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
	}

	/**
	 * Returns a new GraphicsLayer in the given size.
	 *
	 * @param int $width (readonly)
	 * @param int $height (readonly)
	 * @return GraphicsLayer
	 */
	function resize($width, $height) {
		$this->rasterize();
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
	 * Resolve/Allocate palete index for the given $color
	 *
	 * @param string $color Allowed syntax:
	 * 	'red'
	 *  '#f00'
	 *  '#ff0000'
	 *  'rgb(255, 0, 0)'
	 *  'rgba(255, 0, 0, 0.5)'
	 *
	 * @return int
	 */
	protected function allocateColor($color) {
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
		} elseif (preg_match('/^\s{0,}rgba\s{0,}\(\s{0,}([0-9]+)\s{0,},\s{0,}([0-9]+)\s{0,},\s{0,}([0-9]+)\s{0,},\s{0,}([01]{0,1}\.[0-9]+)\s{0,}\)\s{0,}$/', $color, $match)) {
			// rgba(255, 255, 255, 0.5) notation
			$red = $match[1];
			$green = $match[2];
			$blue = $match[3];
			$alpha = ceil((1 - $match[4]) * 127);
			$pallete = imagecolorexactalpha($this->gd, $red, $green, $blue, $alpha); // Resolve color
			if ($pallete !== -1) {
				return $pallete;
			}
			return imagecolorallocatealpha($this->gd, $red, $green, $blue, $alpha); // Allocate color
		} else {
			notice('Unsupported color notation: "'.$color.'"');
			return -1;
		}
		$pallete = imagecolorexact($this->gd, $red, $green, $blue);  // Resolve color
		if ($pallete !== -1) {
			return $pallete;
		}
		return imagecolorallocate($this->gd, $red, $green, $blue); // Allocate color
	}

}

?>
