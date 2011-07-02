<?php
/**
 * GDImage test
 * 
 * @package GD
 */
namespace SledgeHammer;
class GDImageTest extends \UnitTestCase {
	

	function test_jpg() {
		$Image = new GDImage(dirname(__FILE__).'/images/test.jpg');
		$this->assertEqual($Image->width, 132);
	}

	function test_invalid_extention() {
		$filename = dirname(__FILE__).'/images/jpeg_file.gif';
		$restoreValue = ini_get('html_errors');
		ini_set('html_errors', false);
		$this->expectError("imagecreatefromgif(): '".$filename."' is not a valid GIF file");
		$this->expectError('Invalid extention, detected mimetype: "image/jpeg" for "'.$filename.'"'); 
		$Image = new GDImage($filename);
		$this->assertEqual($Image->width, 132);
		ini_set('html_errors', $restoreValue);
	}
}
?>
