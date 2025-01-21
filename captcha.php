<?php
require_once('loader.inc.php');

session_name('TICKONIX_SESSID_'.md5(__DIR__));
session_start();

// variables
$captcha_bg_img 	= __DIR__.'/img/captcha/bg_captcha.png';
$captcha_over_img 	= __DIR__.'/img/captcha/bg_captcha_over.png';
$font_file 			= __DIR__.'/img/captcha/railway-webfont.ttf';
$font_size			= 25;
$text_angle			= mt_rand(0, 5);
$text_x				= mt_rand(0, 18);
$text_y				= 35;
$text_chars 		= 5;
$text_color			= array(mt_rand(0, 80), mt_rand(0, 80), mt_rand(0, 80));

// generate random text
unset($_SESSION['captcha_text']);
$text = randomString($text_chars);
$_SESSION['captcha_text'] = $text;

// header: mime-type image, no-cache
header('Expires: Mon, 26 Jul 1990 05:00:00 GMT');
header("Last-Modified: ".date("D, d M Y H:i:s")." GMT");
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-type: image/png');

// create captcha image
$img = ImageCreateFromPNG($captcha_bg_img);
$text_color = ImageColorAllocate($img, $text_color[0], $text_color[1], $text_color[2]);
imagettftext($img, $font_size, $text_angle, $text_x, $text_y, $text_color, $font_file, $text);
imagecopy($img, ImageCreateFromPNG($captcha_over_img), 0, 0, 0, 0, 140, 40);

// output image
imagepng($img);
imagedestroy($img);
