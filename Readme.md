
# SledgeHammer Image Module

## Datastructure

* Image
  * layers
    * TextLayer
	* GraphicsContainer
      * CanvasLayer
      * ImageLayer
    * ImageLayer


## Creating an image object

### new Image((string) filename)
Loads an into an ImageLayer as base.

### new Image(Graphicslayer)
Use the given GraphicsLayer as a base.

### new Image((int) width, (int) height)
Create an transparent CanvasLayer with the given dimensions as a base.

## Example usage

```php
     $image = new Image('/path/to/my-image.jpg');
     $resized = $image->resized(120, 100);
     $resized->saveTo('/path/to/my-thumb-120x100.png');
```