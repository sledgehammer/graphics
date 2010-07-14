<?php
/**
 * Voor dynamische tekst in een GDImage.
 * Valt terug op build-in fonts als TTF niet ondersteund wordt.
 *
 * @link http://php.net/gd
 * @package GD
 */

class GDText extends Object {

	public
		$text,
		$font, //Naam van het font ("Arial", "New Times Roman", etc) of 1 - 5 voor built-in fonts
		$angle = 0.0,
		$size = 11,
		$bold = false,
		$italic = false,
		$oblique = false;

	static $defaultFont = 'Bitstream Vera Sans';

	private static $fontCache = null;


	/**
	 *
	 * @param string $text
	 * @param array options Denk aan 'font', 'size', 'bold', 'italic',  'angle'
	 */
	function __construct($text, $options = array()) {
		unset($this->width, $this->height);

		$this->text = $text;
		$this->font = self::$defaultFont;
		foreach ($options as $property => $value) {
			$this->$property = $value;
		}
	}

	/**
	 * Use GDImage->drawText(GDText, x, y) to render text on a GDImage.
	 *
	 * @param resource $gd  GD resource
	 * @param int $x
	 * @param int $y
	 * @param int $paleteIndex
	 */
	function renderTo($gd, $x, $y, $colorIndex) {
		if ($this->useBuildinFonts()) {
			$font = $this->getBuildinFontIndex();
			$lines = explode("\n", $this->text); // Simuleer multiline testrendering
			foreach ($lines as $i => $text) {
				imagestring($gd, $font, $x, $y + ($i * imagefontheight($font)), $text, $colorIndex);
			}
			return;
		}
		// Tekst m.b.v. een TTF font.
       	$info = $this->getFontInfo();
		if ($info) {
			$font = $info['filename'];
		} else {
			$font = $this->font; // Niks gevonden, laat GD dan zelf een zoekpoging doen.
		}
		imagefttext($gd, $this->size, $this->angle, $x, $y + ceil($this->size), $colorIndex, $font, $this->text);
   	}

	/**
	 * Vraag de afmetingen op van de text.
	 *
	 * @return array
	 *   array(
	 *     'width' => ?,
	 *     'height' => ?,
	 *     'top' => ?,
	 *     'left' => ?
	 *   );
	 */
	function getBox() {
		if ($this->useBuildinFonts() == false) {
			$info = $this->getFontInfo();
			if ($info === false) {
				$font = $this->font;
			} else {
				$font = $info['filename'];
			}
			$box = imageftbbox($this->size, $this->angle, $font, $this->text);
			if ($box === false) {
				throw new Exception('Unable to determine box for "'.$this->font.'"');
           	}
			$result = array(
				'left' => ($box[0] < $box[6] ? $box[0] : $box[6]),
				'top' => ($box[5] < $box[7] ? $box[5] : $box[7]) + ceil($this->size),
				'width' => ($box[2] > $box[4] ? $box[2] : $box[4]),
				'height' => ($box[1] > $box[3] ? $box[1] : $box[3]) + ceil($this->size),
			);
			if ($result['left'] < 0) {
				$result['width'] += -1 * $result['left'];
			}
			if ($result['top'] < 0) {
				$result['height'] += -1 * $result['top'];
			}

			return $result;
		}
		// Using Buildin font metrics
		$font = $this->getBuildinFontIndex();
		$lines = explode("\n", $this->text);
		$height = (imagefontheight($font) * count($lines));
		$width = 0;
		foreach ($lines as $text) {
			$lineWidth = (imagefontwidth($font) * strlen($text)) - 1;
			if ($lineWidth > $width) {
				$width = $lineWidth;
			}
		}
		$topOffsets = array(
			1 => 1,
			2 => 3,
			3 => 2,
			4 => 3,
			5 => 3,
		);
		if ($font <= 5) {
			$top = $topOffsets[$font];
		} else {
			$top = 0;
		}
		return array(
			'left' => 0,
			'top' => $top,
			'width' => $width,
			'height' => $height,
		);
	}

	/**
	 * Vergroot of verklein het font, zodat deze nog net in de box past.
	 * @param int $width
	 * @param int $height
	 */
	function fitInto($width, $height) {
		// @todo implement beter font resize algoritm.
		for ($i = 2; $i < $height * 2; $i += 0.5) {
			$this->size = $i;
			$box = $this->getBox();
			if ($width < $box['width'] || $height < $box['height']) {
				$this->size = $i - 1;
				return;
			}
		}
	}
	
	/**
	 * Bereken de x offset om de teskt in het midden uit te lijnen
	 * @param int $width 
	 */
	function centerOffset($width) {
		$box = $this->getBox();
		$totalWhitspace = $width - $box['width'];
		return floor($totalWhitspace / 2);
	}

	/**
	 * Doorzoek de fontmappen naar het opgegeven font.
	 *
	 * @return array|false  Geeft false als het font niet gevonden wordt.
	 */
	private function getFontInfo() {
		$font = strtolower($this->font);
		if ($this->bold) {
			$font .= ' bold';
		}
		if ($this->italic) {
			$font .= ' italic';
		}
		if ($this->oblique) {
			$font .= ' oblique';
		}
		$cacheFile = PATH.'tmp/gdtext.fontcache.ini';
		if (self::$fontCache === null && file_exists($cacheFile)) {
			self::$fontCache = parse_ini_file($cacheFile, true);
       	}
		if (isset(self::$fontCache[$font])) {
			if (value(self::$fontCache[$font]['fresh'])) {
				return self::$fontCache[$font];
			} else {
				if (file_exists(self::$fontCache[$font]['filename'])) {
					self::$fontCache[$font]['fresh'] = true;
					return self::$fontCache[$font];
				}
				// Cache file contains invalid data
				file_put_contents($cacheFile, '; Font Cache');
			}
		}
       	$fontFolders = array(
			'/usr/share/fonts/TTF/', // Linux Arch
			'/usr/share/fonts/truetype/', // Linux Ubuntu
			'c:/windows/fonts/', // Windows
			'/Library/Fonts/',  // Mac OSX
			'/usr/X11/lib/X11/fonts/TTF/', // X11
			'/opt/share/fonts/bitstream-vera/' // Optware
		);
		if (is_dir('/usr/share/fonts/truetype/')) { 
			$dir = new DirectoryIterator('/usr/share/fonts/truetype/');
			foreach ($dir as $entry) {
				if ($entry->isDot() == false && $entry->isDir()) {
					$fontFolders[] = $entry->getPathname().'/';
				}
			}
		}
		foreach ($fontFolders as $folder) {
			if (is_dir($folder) == false) {
				continue;
            }
			// Het font heeft een andere bestandnaam (file_exists is hoofdlettergevoelig)
            $dir = new DirectoryIterator($folder);
            foreach ($dir as $entry) {
                $filename = $entry->getFilename();
				if (substr($filename, 0, 1) == '.') {
                    // directories en verborgen bestanden overslaan
					continue;
                }
				if (in_array(strtolower(file_extension($filename)), array('ttf', 'otf')) == false) {
					// Negeer alles behalve de *.ttf en *.otf bestanden.
					continue;
				}
				//if (preg_match('/'.str_replace(' ', '|', preg_quote($this->font, '/')).'/i', $filename) == 0) {
				//	continue;
				//}
				$ttf = new TrueTypeFont($entry->getPathname());
				$properties = $ttf->getNameTable();
				unset($ttf);
				if (!isset($properties['1::0::0'][2])) {
					//dump($properties);
					continue;
				}
				$info = array(
					'filename' => $entry->getPathname(),
					'name' => $properties['1::0::0'][1],
					'type' => $properties['1::0::0'][2],
					'alias' => strtolower($properties['1::0::0'][3]),
				);
				$info['fresh'] = true;
				$id = strtolower($info['name'].' '.$info['type']);
				self::$fontCache[$id] = $info;
				if ($id != $info['alias']) {
					self::$fontCache[$info['alias']] = $info;
				}
			}
		}
		if (empty(self::$fontCache[$font])) {
			self::$fontCache[$font] = false;
		} else {
			if (value(self::$fontCache[$font]['fresh']) === true) { // Is deze font entry zojuist gedetecteerd?
				// Toevoegen aan de cache
				$info = self::$fontCache[$font];
				$contents = file_exists($cacheFile) ? file_get_contents($cacheFile) : "; Font Cache";
				$contents .= "\n\n";
				$contents .= '['.$font."]\n";
				$contents .= '  filename = "'.$info['filename']."\"\n";
				$contents .= '  name = "'.$info['name']."\"\n";
				$contents .= '  type = "'.$info['type']."\"\n";
				file_put_contents($cacheFile, $contents);
           	}
		}
		return self::$fontCache[$font];
	}

	/**
	 * Geeft aan of er gebruik gemaakt moet worden van de build-in fonts.
	 *
	 * @return bool
	 */
	private function useBuildinFonts() {
		if (is_int($this->font)) {
			return true;
		}
		$capabilities = gd_info();
		return ($capabilities['FreeType Support'] == false);
	}

	/**
	 * Geeft het interne font o.p.v. de $this->size
	 * Als $this->font een integer is, wordt deze gebruikt.
	 *
	 * @return int
	 */
	private function getBuildinFontIndex() {
		if (is_int($this->font)) { // Is er een specifieke buildin font geselecteerd?
			return $this->font;
		}
		// Voor de grootte (size) is uitgegaan van vergelijkingen met het "Bitstream Vera Sans" font.
		if ($this->size <= 9) {
			return 1; // grootte is ca 7px
		} elseif ($this->size > 9 && $this->size <= 12) { // 10 t/m 12
			return ($this->bold ? 3 : 2); // grootte is ca 11px
		} else { // 13px and up
			return ($this->bold ? 5 : 4); // grootte is 12 a 13px
		}
	}
}
?>
