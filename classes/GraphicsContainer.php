<?php
namespace SledgeHammer;
/**
 * A collection of GraphicsLayers
 *
 */
class GraphicsContainer extends GraphicsLayer {

	/**
	 * @var array|GraphicsLayer Array containing GraphicsLayer objects
	 */
	protected $layers;

	function __construct($layers = array(), $x = 0, $y = 0) {
		parent::__construct(null, $x, $y);
		$this->layers = $layers;
	}

	/**
	 * Add a layer on top of the other layers
	 *
	 * @param GraphicsLayer $layer
	 * @param string $name (optional) unique key for the layer
	 */
	function prependLayer($layer, $name = null) {
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
		return $this->layers[$name];
	}

	protected function rasterize() {
		if ($this->gd !== null) {
			imagedestroy($this->gd); // Free memory
			$this->gd = null;
		}
		$count = count($this->layers);
		if ($count === 0) {
			return parent::rasterize(); // return a nixel
		}
		$width = $this->width;
		$height = $this->height;
		if ($count === 1) {
			reset($this->layers);
			$layer = current($this->layers);
			if ($layer->x == 0 && $layer->y == 0 && $width === $layer->width && $height === $layer->height) {
				// The container only contains 1 layer.
				return $layer->rasterize();
			}
		}
		$this->gd = $this->createCanvas($width, $height);
		foreach (array_reverse($this->layers) as $layer) {
			$layer->rasterizeTo($this->gd, $layer->x, $layer->y);
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
		if ($this->gd !== null) {
			return imagex($this->gd);
		}
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
		if ($this->gd !== null) {
			return imagey($this->gd);
		}
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
