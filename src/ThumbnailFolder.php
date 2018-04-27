<?php

namespace Sledgehammer\Graphics;

use Sledgehammer\Core\Url;
use Sledgehammer\Mvc\Component\HttpError;
use Sledgehammer\Mvc\Folder;
use Sledgehammer\Mvc\Document\File;

/**
 * Een Virtual folder die aan de hand van de mapnaam de afmetingen van de thumbnail bepaald.
 * De Url /160x120/MyImage.jpg zal van de afbeelding MyImage.jpg een thumbnail maken van 160px breed en 120px hoog.
 */
class ThumbnailFolder extends Folder
{
    protected $imagesFolder;
    public $targetFolder;

    public function __construct($imagesFolder, $targetFolder = null)
    {
        parent::__construct();
        $this->imagesFolder = $imagesFolder;
        if ($targetFolder == null) {
            $targetFolder = \Sledgehammer\TMP_DIR.'ThumbnailFolder/'.basename($imagesFolder).'_'.substr(md5($imagesFolder), 8, 16).'/';
        }
        $this->targetFolder = $targetFolder;
    }

    public function file($filename)
    {
        if ($this->isImage($filename)) {
            return new File($this->imagesFolder.$filename);
        }

        return $this->onFileNotFound();
    }

    public function folder($folder, $filename = null)
    {
        if (!preg_match('/^[0-9]+x[0-9]+$/', $folder)) { // Zijn er geen afmetingen meegegeven?
            return $this->onFolderNotFound();
        }
        if (!$filename) { // Komt de afbeelding uit een subfolder($recursive)?
            $url = Url::getCurrentURL();
            $path = [
                'folders' => $url->getFolders(),
                'filename' => $url->getFilename(),
            ];
            $subfolders = array_slice($path['folders'], $this->depth + 1);
            $filename = implode('/', $subfolders).'/'.$path['filename'];
        }
        $source = $this->imagesFolder.$filename;
        if (!file_exists($source)) {
            return new HttpError(404, ['warning' => 'Image "'.$filename.'" not found in "'.$this->imagesFolder.'"']);
        }
        $target = $this->targetFolder.$folder.'/'.$filename;
        if (!file_exists($target) || filemtime($source) > filemtime($target)) {
            $dimensions = explode('x', $folder);
            \Sledgehammer\mkdirs(dirname($target));
            $image = new Image($source);
            $image->saveThumbnail($target, $dimensions[0], $dimensions[1]);
        }

        return new File($target);
    }

    protected function isImage($filename)
    {
        return substr(\Sledgehammer\mimetype($filename, true), 0, 6) == 'image/';
    }
}
