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
	private $color;

	function __construct($width, $height = null) {
		$this->gd = imagecreatetruecolor($width, $height);
		// Set transparent background
		imagealphablending($this->gd, false);
		imagefilledrectangle($this->gd, 0, 0, $width, $height, imagecolorallocatealpha($this->gd, 255, 255, 255, 127));
		imagealphablending($this->gd, true);
		// Set brush color to opaque black
		$this->color = imagecolorallocate($this->gd, 0, 0, 0);
	}

	/**
	 * Return the current color (palette index)
	 *
	 * @return int
	 */
	function getColor() {
		return $this->color;
	}
	/**
	 * Set
	 *   setColor('ffffff');
	 *     of
	 *   setColor('rgb(255, 255, 255)')
	 *
	 * @param string $color  html-kleurcode
	 * @param float $alpha 0 = invisible, 0.5 = 50% transparent, 1 = opaque
	 * @return void
	 */
	function setColor($color) {
		$this->color = $this->allocateColor($color);
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
