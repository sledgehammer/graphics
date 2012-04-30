<?php
namespace SledgeHammer;
/**
 * Image, a grapical container
 *
 * Responsibility:
 *   Output (Compaible with the View interface)
 *   Creating thumbnails
 *   Container for a treestructure of GraphicsLayer's
 *
 * @package Image
 */
class Image extends GraphicsContainer {

	/**
	 * @var string mimetype of the rendered output
	 */
	public $mimetype = 'image/png';

	/**
	 * @var int quality paramerer for when the output is a jpeg.
	 */
	public $jpegQuality = 80;

	/**
	 * Usage:
	 *  new Image(200, 150); // Image with a 200x150 canvas
	 *  new Image('/tmp/upload.jpg'); // Image with a background from the file
	 *  new Image(new TextLayer('Hi')); // Image with a GraphicsLayer as background
	 *  new Image(array(new TextLayer('Hi'), new ImageLayer('/tmp/upload.jpg')); // Image with a 2 layers
	 *
	 * @param $mixed
	 */
	function __construct($mixed) {
		if (!function_exists('gd_info')) {
			throw new \Exception('Required PHP extension "GD" is not loaded');
		}
		if (is_array($mixed)) {
			parent::__construct($mixed);
			return;
		}
		if (is_numeric($mixed) && func_num_args() == 2) {
			$layer = new CanvasLayer($mixed, func_get_arg(1));
			$this->width = $mixed;
			$this->height = func_get_arg(1);
		} elseif (is_string($mixed)) {
			$layer = new ImageLayer($mixed);
		} elseif (is_object($mixed) && $mixed instanceof GraphicsLayer) {
			$layer = $mixed;
		} else {
			throw new InfoException('Argument 1 is invalid, expecting a filename, GraphicsLayer or dimentions', $mixed);
		}
		parent::__construct(array('background' => array('graphics' => $layer, 'position' => array('x' => 0, 'y' => 0))));
	}

	/**
	 * Create a thumbnail
	 *
	 * @param string $filename
	 * @param int $width
	 * @param int $height
	 */
	function saveThumbnail($filename, $width = 100, $height = 100) {
		$sourceWidth = $this->width;
		$sourceHeight = $this->height;
		$ratio = $width / $height;

		$diff = $ratio - ($sourceWidth / $sourceHeight);
		if ($diff < 0) {
			$diff *= -1;
		}
		if ($diff < 0.1) { // Ignore small changes in aspect ratio
			$cropped = $this;
		} else {
			// Crop image to correct aspect ratio
			if ($ratio * $sourceHeight < $sourceWidth) { // Discard a piece from the left & right
				$cropped = $this->cropped(round($ratio * $sourceHeight), $sourceHeight);
			} else { // Discard a piece from the top & bottom
				$cropped = $this->cropped($sourceWidth, round($sourceWidth / $ratio));
			}
		}
		$thumbnail = $cropped->resized($width, $height);
		$options = array(
			'quality' => 75
		);
		if ($width < 200) { // Small thumbnail?
			$options['quality'] = 60;
		}
		$thumbnail->saveTo($filename, $options);
	}

	// Compatible with View/Document interface

	/**
	 * This View can not be nested inside another view
	 * @return bool
	 */
	function isDocument() {
		return true;
	}

	function getHeaders() {
		return array(
			'http' => array('Content-Type' => $this->mimetype)
		);
	}

	function render() {
		$this->saveTo(null, array(
			'mimetype' => $this->mimetype,
			'quality' => $this->jpegQuality,
		));
	}

}

?>
