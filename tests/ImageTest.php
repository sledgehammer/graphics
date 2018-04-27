<?php

namespace SledgehammerTests\Graphics;

use PHPUnit\Framework\Error\Warning;
use Sledgehammer\Graphics\Image;
use SledgehammerTests\Core\TestCase;

/**
 * Image test.
 */
class ImageTest extends TestCase
{
    public function test_jpg()
    {
        $Image = new Image(dirname(__FILE__).'/images/test.jpg');
        $this->assertEquals($Image->width, 132);
    }

    public function test_invalid_extension_warning()
    {
        $this->expectException(Warning::class);
        $filename = dirname(__FILE__).'/images/jpeg_file.gif';
        $image = new Image($filename);
        $image->height;
    }

    public function test_extension_autocorrection()
    {
        $filename = dirname(__FILE__).'/images/jpeg_file.gif';
        $image = new Image($filename);
        $this->assertEquals(@$image->width, 132);
    }
}
