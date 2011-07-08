<?php
/**
 * Afbeelding object georienteerd bewerken ala Canvas.
 * Werkt met de GD functies.
 *
 * Kan als Component functioneren
 *
 * @link http://php.net/gd
 * @package GD
 */
namespace SledgeHammer;
class GDImage extends Object {

	public 
		$jpegQuality = 75;

	//read only (dynamic) properties
	public
		$width,
		$height,
		$aspectRatio;

	protected
		$gd; // De GD resource

	private 
		$render_mimetype, // Het afbeelding type (jpg, gif, png) dat gebruikt wordt voor de render() functie
		$render_to_file = NULL, // 
		$activeColor; // Een GD color resource

	/**
	 * @param string|array Het afbeelding bestand dat geopent moet worden. of een array('width' => 100, 'height' => 150) waarmee een nieuw canvas gecreert word.
	 */
	function __construct($filename_or_dimentions, $render_mimetype = 'image/png') {
		if (!function_exists('gd_info')) {
				throw new \Exception('php extention "GD" is required');
		}
		unset($this->width, $this->height, $this->aspectRatio); // Ervoor zorgen dat deze eigenschappen via de __get() worden opgevraagd

		if (strpos($render_mimetype, '/') === false) {
			$render_mimetype = 'image/'.$render_mimetype;
		}
		$this->render_mimetype = strtolower($render_mimetype);

		if (is_array($filename_or_dimentions)) {
			// Een nieuwe afbeelding aanmaken (in het geheugen)
			$dimentions = $filename_or_dimentions;
			$this->gd = imagecreatetruecolor($dimentions['width'], $dimentions['height']);
		} else {
			// Een afbeelding bestand openen
			$filename = $filename_or_dimentions;
			$mimetype = mimetype($filename, true, 'UNKNOWN');
			if ($mimetype == 'UNKNOWN' || $this->load($mimetype, $filename) == false) {

				$imageInfo = getimagesize($filename);
				if (isset($imageInfo['mime'])) {
					$detectedMimetype = $imageInfo['mime'];
				} else {
					notice('Imagetype detection failed');
					$detectedMimetype = false;
				}
				if ($detectedMimetype === false || $this->load($detectedMimetype, $filename) == false) {
					throw new \Exception('Unable to load "'.$filename.'"');
				}
				if ($mimetype != 'UNKNOWN') {
					notice('Invalid extention, detected mimetype: "'.$detectedMimetype.'" for "'.$filename.'"');
				}
			}
		}
		if(!imageistruecolor($this->gd)) { // is de gd niet in 'ware kleuren' en bevat de afbeelding geen alpha kanaal?
			$palette = imagecolorstotal($this->gd);
			imagecolortransparent($this->gd);
			if($palette == imagecolorstotal($this->gd)) { // Bevatte het palette al een transparante kleur?
				// Niet omzetten naar truecolor (dan verliest hij zijn alpha kanaal)
			} else {
				// De gd omzetten naar 'ware kleuren'.
				$this->rgbMode();
			}
		}
		if(function_exists('imageantialias')) {
			imageantialias($this->gd, true); // Gebruik anti aliasing voor het tekenen van lijnen
		}
	}

	function __destruct() {
		if ($this->gd) {
			imagedestroy($this->gd); // De afbeelding in het geheugen opschonen
		}
	}

	function getHeaders() {
		return array('http' => array(
			'Content-Type' => $this->render_mimetype
		));

	}
	function render() {
		if ($this->render_to_file === NULL) {
			$mimetype = $this->render_mimetype;
			$errorMessage = 'Failed to render the image';
		} else {
			$mimetype = mimetype($this->render_to_file);
			$errorMessage = 'Failed to save the image to "'.$this->render_to_file.'"';
		}
		if ($mimetype == 'image/jpeg') {
			if (!imagejpeg($this->gd, $this->render_to_file, $this->jpegQuality)) {
				throw new \Exception($errorMessage);
			}
			return;
		}
		$mimetype_to_function = array(
			'image/png' => 'imagepng',
			'image/gif' => 'imagegif',
		);
		$mimetype = $this->render_mimetype;
		if (isset($mimetype_to_function[$mimetype])) {
			$function = $mimetype_to_function[$mimetype];
			if (!$function($this->gd, $this->render_to_file)) {
				throw new \Exception($errorMessage);
			}
		} else {
			warning('Unsupported mimetype: "'.$mimetype.'"');
		}
	}

	/**
	 * De afbeelding opslaan
	 */
	function save_as($filename) {
		$this->render_to_file = $filename;
		$this->render();
		$this->render_to_file = NULL;
	}

	function __get($property) {
		switch ($property) {

			case 'width':
				return imagesx($this->gd);

			case 'height':
				return imagesy($this->gd);

			case 'aspectRatio':
				return imagesx($this->gd) / imagesy($this->gd);
		}
		return parent::__get($property);
	}

	/**
	 *  Het formaat van de afbeelding aanpassen
	 *
	 * @param int $width
	 * @param int $height
	 * @param array $options (
	 *   'resample' => bool, // default true
	 *   'maintain_aspect_ratio' => true,false,'crop' // default false
	 * )    
	 * @return void
	 */
	function resize($width, $height, $options = array()) {

		// Defaults
		$resample = true; // hoge qualiteit
		$maintain_aspect_ratio = false; // Negeer aspect ratio

		// Parse options
		foreach ($options as $option => $value) {
			switch ($option) {

				case 'resample':
					if (is_bool($value) == false) {
						notice('Unexpected '.$option.'-value: "'.$value.'", expecting true or false');
					}
					$resample = $options['resample'];
					break;

				case 'maintain_aspect_ratio':
					if (is_bool($value) == false && $value != 'crop') {
						notice('Unexpected '.$option.'-value: "'.$value.'", expecting "crop", true or false');
					}
					$maintain_aspect_ratio = $value;
					break;

				default:
					notice('Unexpected option: "'.$option.'", expecting "resample" or "maintain_aspect_ratio"');
					break;
			}
		}
		if ($maintain_aspect_ratio === 'crop') { // De afbeelding bijsnijden zodat de verhoudingen blijven kloppen.
			$this->crop_to_aspect_ratio($width / $height);
		} elseif ($maintain_aspect_ratio) { // Breedte of hoogte aanpassen zodat de verhoudingen weer kloppen.
			$current_ratio = $width / $height;
			$target_ratio = $this->aspectRatio;
			if ($target_ratio < $current_ratio) { // te breed
				$width = $height * $target_ratio;	
			} elseif ($target_ratio > $current_ratio) { // te lang
				$height = $width / $target_ratio;	
			}
		}
		//if($current_width == $width && $current_height == $height) { return } // Geen aanpassing nodig?
		$gd = imagecreatetruecolor($width, $height);
		if ($resample) {
			$copyfunction = 'imagecopyresampled';
		} else {
			$copyfunction = 'imagecopyresized';
		}
		$copyfunction($gd, $this->gd, 0, 0 , 0, 0, $width, $height, $this->width, $this->height);
		imagedestroy($this->gd);
		$this->gd = $gd;
	}

	/**
	 * De afbeelding bijsnijden (crop)
	 */
	function crop($x, $y, $width, $height) {
		$gd = imagecreatetruecolor($width, $height);
		imagecopy($gd, $this->gd, 0, 0 , $x, $y, $width, $height);
		imagedestroy($this->gd);
		$this->gd = $gd;
	}


	/**
	 * ratio instellen
	 * @param float $ratio
	 */
	function crop_to_aspect_ratio($ratio) {
		$x = 0;
		$y = 0;
		if($ratio * $this->height < $this->width) { // links en rechts een stuk afsnijden
			$height = $this->height;
			$width = round($ratio * $this->height);
			$x = round(($this->width - $width) / 2);
		} else { // Boven en beneden een stuk afsnijden
			$width = $this->width;
			$height = round($this->width / $ratio);
			$y = round(($this->height - $height) / 2);
		}
		$this->crop($x, $y, $width, $height); // De afbeelding bijsnijden
	}

	/**
	 * Afbeelding omzetten naar indexed-mode met een beperkt palet.
	 *
	 * @param int $colorCount
	 * @param true $dither
	 * @return void
	 */
	function indexedMode($colorCount, $dither = true) {
		imagetruecolortopalette($this->gd, $dither, $colorCount);
	}

	/**
	 * Afbeelding omzetten naar ware kleuren (16.7M kleuren)
	 *
	 * @return void
	 */
	function rgbMode() {
		$gd = imagecreatetruecolor($this->width, $this->height);
		if(function_exists('imageantialias')) {
			imageantialias($gd, true); // Gebruik anti aliasing voor het tekenen van lijnen
		}
		imagecopy($gd, $this->gd, 0, 0, 0, 0, $this->width, $this->height);
		imagedestroy($this->gd);
		$this->gd = $gd;
	}

	/**
	 * De kleur instellen die word gebruikt in de tekenfuncties.
	 *   setColor('ffffff');
	 *     of
	 *   setColor(255, 255, 255)
	 * 
	 * @param string $color  html-kleurcode
	 * @return void
	 */
	function setColor($color) {
		$this->activeColor = $this->getColor($color);
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
	function getColor($color = null) {
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
		
		$paletIndex = imagecolorexact($this->gd, $red, $green, $blue); // de kleur opvragen uit het palet.
		
		if ($paletIndex !== -1) {
			return $paletIndex;
		}
		return imagecolorallocate($this->gd, $red, $green, $blue); // De kleur toevoegen aan het palet
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

	/**
	 *
	 * @param GDText|string $text
	 * @param int $x
	 * @param int $y
	 * @param string $color
	 */
	function text($text, $x, $y, $color = null) {
		if (is_string($text)) {
			$text = new GDText($text);
		}
		$text->renderTo($this->gd, $x, $y, $this->getColor($color));
    }

	/**
	 * Een gedeelte van deze afbeelding opvragen.
	 * 
	 * @return GDImage
	 */
	function copy($x, $y, $width, $height) {
		$image = new GDImage(array('width' => $width, 'height' => $height), $this->render_mimetype);
		imagecopy($image->gd, $this->gd, 0, 0, $x, $y, $width, $height);
		return $image;
	}

	/**
	 * Een afbeelding in deze afbeelding plakken
	 *
	 * @param GDImage $image
	 * @param int $x
	 * @param int $y
	 */
	function paste($image, $x, $y) {
		imagecopy($this->gd, $image->gd, $x, $y, 0, 0, $image->width, $image->height);
	}
	
	/**
	 * Dit Component kan niet binnen een ander component getoond worden. 
	 * @return bool
	 */	
	function isDocument() {
		return true;
	}

	private function load($mimetype, $filename) {
		$mimetype_to_function = array(
			'image/png' => 'imagecreatefrompng',
			'image/gif' => 'imagecreatefromgif',
			'image/jpeg' => 'imagecreatefromjpeg',
			'image/bmp' => 'imagecreatefrombmp',
		);
		if (isset($mimetype_to_function[$mimetype])) {
			$function = $mimetype_to_function[$mimetype];
			$this->gd = $function($filename);
		} else {
			notice('Unsupported mimetype: "'.$mimetype.'" for "'.$filename.'"');
		}
		if ($this->gd) {
			return true;
		}
		return false;
	}
}
?>
