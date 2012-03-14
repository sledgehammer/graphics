<?php
/**
 * GDImage test
 *
 * @package GD
 */
namespace SledgeHammer;
class GDImageTest extends TestCase {

	function test_jpg() {
		$Image = new GDImage(dirname(__FILE__).'/images/test.jpg');
		$this->assertEquals($Image->width, 132);
	}

	/**
	 * @expectedException \PHPUnit_Framework_Error_Warning
	 */
	function test_invalid_extension_warning() {
		$filename = dirname(__FILE__).'/images/jpeg_file.gif';
		new GDImage($filename);
	}

	function test_extension_autocorrection() {
		$filename = dirname(__FILE__).'/images/jpeg_file.gif';
		$image = @new GDImage($filename);
		$this->assertEquals($image->width, 132);
	}

}
?>
