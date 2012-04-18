<?php
namespace SledgeHammer;
/**
 * CanvasLayer
 *
 * Responsibility:
 *   Drawing lines, rectangles, etc
 */
class CanvasLayer extends GraphicsLayer {

	/**
	 * @var int  The index for the current color, (default: black)
	 */
	private $activeColor;

	function __construct($width, $height = null) {
		$this->gd = imagecreatetruecolor($width, $height);
		imagealphablending($this->gd, false);
		imagesavealpha($this->gd, true);
		$transparent = imagecolorallocatealpha($this->gd, 255, 255, 255, 127);
		imagefilledrectangle($this->gd, 0, 0, $width, $height, $transparent);

		if (function_exists('imageantialias')) {
			imageantialias($this->gd, true); // Gebruik anti aliasing voor het tekenen van lijnen
		}
		$this->activeColor = imagecolorallocatealpha($this->gd, 0, 0, 0, 0); // opaque black
	}

	/**
	 * De kleur instellen die word gebruikt in de tekenfuncties.
	 *   setColor('ffffff');
	 *     of
	 *   setColor(255, 255, 255)
	 *
	 * @param string $color  html-kleurcode
	 * @param float $alpha 0 = invisible, 0.5 = 50% transparent, 1 = opaque
	 * @return void
	 */
	function setColor($color, $alpha = null) {
		$this->activeColor = $this->getColor($color, $alpha);
	}

	/**
	 * Vraag de palet index op van de kleur.
	   * getColor()   // actieve kleur
	 *     of
	 *   getColor('ffffff');
	 *     of
	 *   getColor('rgb(255, 255, 255)')
	 *
	 * @param string $color red of html-kleurcode
	 * @return int
	 */
	function getColor($color = null, $alpha = null) {
		if ($color === null) {
			return $this->activeColor;
		}
		$color = strtolower($color);
		$colorNames = array(
			'black'   => '000000',
			'red'     => 'ff0000',
			'lime'   => '00ff00',
			'blue'    => '0000ff',
			'yellow'  => 'ffff00',
			'aqua'    => '00ffff',
			'fuchsia' => 'ff00ff',
			'white'   => 'ffffff',
			'silver'  => 'c0c0c0',
			'gray'    => '808080',
			'purple'  => '800080',
			'maroon'  => '800000',
			'green'   => '008000',
			'olive'   => '808000',
			'navy'    => '000080',
			'teal'    => '008080',
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
			// rgb(255, 255, 255) notatie
			$red = $match[1];
			$green = $match[2];
			$blue = $match[3];
		} else {
			notice('Unsupported color specification: "'.$color.'"');
			return -1;
		}
		if ($alpha === null) {
			$paletIndex = imagecolorexact($this->gd, $red, $green, $blue); // de kleur opvragen uit het palet.

			if ($paletIndex !== -1) {
				return $paletIndex;
			}
			return imagecolorallocate($this->gd, $red, $green, $blue); // De kleur toevoegen aan het palet
		} else {
			$alpha = ceil(($alpha - 1) * 127);
			$paletIndex = imagecolorexactalpha($this->gd, $red, $green, $blue, $alpha); // de kleur opvragen uit het palet.

			if ($paletIndex !== -1) {
				return $paletIndex;
			}
			return imagecolorallocatealpha($this->gd, $red, $green, $blue, $alpha); // De kleur toevoegen aan het palet
		}

	}

	/**
	 * Vul de gehele gd met de meegegeven kleur
	 * @param string $color  Bv: 'ddeeff'
	 */
	function fill($color = null) {
		imagefilledrectangle ($this->gd, 0, 0, $this->width, $this->height, $this->getColor($color));
	}

	function dot($x, $y, $color = null) {
		imagesetpixel($this->gd, $x, $y, $this->getColor($color));
	}

	function line($x1, $y1, $x2, $y2, $color = null) {
		imageline($this->gd, $x1, $y1, $x2, $y2, $this->getColor($color));
	}

	function rectangle($x, $y, $width, $height, $color = null) {
		imagerectangle($this->gd, $x, $y, $x + $width - 1, $y + $height - 1, $this->getColor($color));
	}

	function fillRectangle($x, $y, $width, $height, $color = null) {
		imagefilledrectangle($this->gd, $x, $y, $x + $width - 1, $y + $height - 1, $this->getColor($color));
	}

}

?>
