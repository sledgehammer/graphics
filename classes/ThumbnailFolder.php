<?php
/**
 * Een Virtual folder die aan de hand van de mapnaam de afmetingen van de thumbnail bepaald.
 * De Url /160x120/MyImage.jpg zal van de afbeelding MyImage.jpg een thumbnail maken van 160px breed en 120px hoog
 *
 * @package GD
 */
class ThumbnailFolder extends VirtualFolder {

	protected
		$imagesFolder;
	
	public
		$targetFolder;

	function  __construct($imagesFolder, $targetFolder = null) {
		parent::__construct();
		$this->imagesFolder = $imagesFolder;
		if ($targetFolder == null)
		{
			$targetFolder = PATH.'tmp/thumbnails/'.str_replace(array('\\','/'), '@', $imagesFolder).'/';
		}
		$this->targetFolder = $targetFolder;
	}

	function dynamicFilename($filename)  {
		if ($this->isImage($filename)) {
			return new FileDocument($this->imagesFolder.$filename);
		}
		return $this->onFileNotFound();
	}

	function dynamicFoldername($folder, $filename = null) {
		if (!preg_match('/^[0-9]+x[0-9]+$/', $folder)) { // Zijn er geen afmetingen meegegeven?
			return $this->onFolderNotFound();
		}
		if (!$filename) { // Komt de afbeelding uit een subfolder($recursive)?
			$path = URL::extract_path();
			$subfolders = array_slice($path['folders'], $this->depth + 1);
			$filename = implode('/',$subfolders).'/'.$path['filename'];
		}
		$source = $this->imagesFolder.$filename;
		if (!file_exists($source)) {
			notice('Image "'.$source.'" not found"');
			return new HttpError(404);
		}
		$target = $this->targetFolder.$folder.'/'.$filename;
		if (!file_exists($target) || filemtime($source) > filemtime($target)) {
			$dimensions = explode('x', $folder);
			$image = $this->createThumbnail($source, $dimensions[0], $dimensions[1]);
			mkdirs(dirname($target));
			$image->save_as($target);
		}
		return new FileDocument($target);
	}

	protected function createThumbnail($source, $width, $height) {
		$image = new GDImage($source);
		if ($image->width < $width && $image->height < $height) { // Is het bronbestand kleiner dan de thumbnail?
			return $image;
		}
		if ($width < 200) { // small thumbnail ?
			$image->jpegQuality = 60;
		} else { // grote thumbnail
			$image->jpegQuality = 75;
		}

		$start = microtime(true);
		$factor = $image->width / $width;
		if ($factor > 3) { // Moet de afbeelding meer dan 3x verkleint worden?
			// Verklein eerst zonder resample, dan met. (speed optim)
			$fast_width = ceil($width * 2);
			$fast_height = ceil($fast_width / $image->aspectRatio);
			$image->resize($fast_width, $fast_height, array('resample' => false));
			$image->resize($width, $height, array('resample' => true, 'maintain_aspect_ratio' => true));
		} else {
			$image->resize($width, $height, array('resample' => true, 'maintain_aspect_ratio' => true));
		}
		return $image;
	}

	protected function isImage($filename) {
		return substr(mimetype($filename, true), 0, 6) == 'image/';
	}
}
?>
