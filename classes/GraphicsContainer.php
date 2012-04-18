<?php
namespace SledgeHammer;
/**
 * A collection of GraphicsLayers
 *
 */
class GraphicsContainer extends GraphicsLayer {

	/**
	 * @var array An index array containing all layers
	 */
	protected $layers = array();

	/**
	 * Add a layer on top of the other layers
	 *
	 * @param GraphicsLayer $layer
	 */
	function prependLayer($layer, $name = null) {
		$this->container = $this;
		if ($name === null) {
			array_unshift($this->layers, $layer);
			return;
		}
		if (array_key_exists($name, $this->layers)) {
			notice('Removing existing layer: "'.$name.'"');
			unset($this->layers[$name]);
		}
		$layers = array_reverse($this->layers);
		$layers[$name] = $layer;
		$this->layers = array_reverse($layers);
	}
	function getLayer($name) {

	}

	function rasterize() {
		if ($this->gd !== null) {
			return $this->gd; // Should validate if a layer has changed? can we?
		}
		$count = count($this->layers);
		if ($count === 0) {
			return parent::rasterize(); // return
		}
		if ($count === 1) {
			reset($this->layers);
			$layer = current($this->layers);
			if ($layer->x == 0 && $layer->y == 0 && $this->width === $layer->width && $this->height === $layer->height) {
				// The container only contains 1 layer.
				return $layer->rasterize();
			}
		}
		// Create tranparent gd resource
		$width = $this->width;
		$height = $this->height;
		$this->gd = imagecreatetruecolor($width, $height);
		$white = imagecolorallocate($this->gd, 255, 255, 255);
		imagefilledrectangle($this->gd, 0, 0, $width, $height, $white);
		imagecolortransparent($this->gd, $white);

		foreach (array_reverse($this->layers) as $layer) {
			$gd = $layer->rasterizeTrueColor();
			imagealphablending($gd, false);
			imagesavealpha($gd, true);

			imagecopy($this->gd, $gd, $layer->x, $layer->y, 0, 0, $layer->width, $layer->height);
		}
		return $this->gd;
	}

	function __set($property, $value) {
		if ($property === 'width' || $property === 'height') {
			$this->$property = $value; // Allow setting the width and height
		}
		parent::__set($property, $value);

	}

	/**
	 * When no width is set, calculate the width
	 *
	 * @return int
	 */
	function getWidth() {
		$maxWidth = 0;
		foreach ($this->layers as $layer) {
			$width = $layer->x + $layer->width;
			if ($width > $maxWidth) {
				$maxWidth = $width;
			}
		}
		return $maxWidth;
	}

	/**
	 * When no height is set, calculate the height
	 *
	 * @return int
	 */
	function getHeight() {
		$maxHeight = 0;
		foreach ($this->layers as $layer) {
			$height = $layer->y + $layer->height;
			if ($height > $maxHeight) {
				$maxHeight = $height;
			}
		}
		return $maxHeight;
	}
}

?>
