<?php
namespace SledgeHammer;
/**
 * Image
 *
 * Responsibility:
 *   Output (render & save)
 *   Utility methods for common scenario's
 *   Container for tree of
 *
 */
class Image extends GraphicsContainer {

	/**
	 * @var string mimetype of the output
	 */
	public $mimetype = 'image/png';


	/**
	 * @var int quality paramerer for when the output is a jpeg.
	 */
	public $jpegQuality = 75;

	function __construct($mixed, $height = null) {
		if (!function_exists('gd_info')) {
			throw new \Exception('Required PHP extension "GD" is not loaded');
		}
		if (is_numeric($mixed)) {
			$this->layers['background'] = new CanvasLayer($mixed, $height);
		} elseif (is_string($mixed)) {
			$this->layers['background'] = new ImageLayer($mixed);
		} elseif (is_object($mixed) && $mixed instanceof GraphicsLayer) {
			$this->layers['background'] = $mixed;
		} else {
			throw new InfoException('Argument 1 is invalid, expecting a filename, GraphicsLayer or dimentions', $mixed);
		}
		$this->layers['background']->container = $this;
	}

	/**
	 * De afbeelding opslaan
	 */
	function saveTo($filename) {
		if ($filename === NULL) {
			$mimetype = $this->mimetype;
			$error = 'Failed to render the image';
		} else {
			$mimetype = mimetype($filename);
			$error = 'Failed to save the image to "'.$filename.'"';
		}
		if ($mimetype == 'image/jpeg') {
			if (!imagejpeg($this->rasterize(), $filename, $this->jpegQuality)) {
				throw new \Exception($error);
			}
			return;
		}
		$mimetype_to_function = array(
			'image/png' => 'imagepng',
			'image/gif' => 'imagegif',
		);
		if (isset($mimetype_to_function[$mimetype])) {
			$function = $mimetype_to_function[$mimetype];
			if (!$function($this->rasterize(), $filename)) {
				throw new \Exception($error);
			}
		} else {
			warning('Unsupported mimetype: "'.$mimetype.'"');
		}
	}

	function saveThumbnail($filename, $width = 100, $height = 100) {

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
		return array('http' => array(
			'Content-Type' => $this->mimetype
		));
	}

	function render() {
		$this->saveTo(null);
	}
}

?>
