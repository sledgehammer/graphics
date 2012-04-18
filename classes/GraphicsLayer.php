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
	public $x = 0; // left
	public $y = 0; // top
	/**
	 * @var GraphicsContainer
	 */
	protected $container;

	function __construct($gd, $x = 0, $y = 0) {
		$this->gd = $gd;
		$this->x = $x;
		$this->y = $y;
	}
	/**
	 * Cleanup
	 */
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

}

?>
