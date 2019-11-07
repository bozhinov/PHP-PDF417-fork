<?php

require_once("bootstrap.php");

use PDF417\PDF417;
use PDF417\pColor;

// Text to be encoded into the barcode
$text = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Curabitur
imperdiet sit amet magna faucibus aliquet. Aenean in velit in mauris imperdiet
scelerisque. Maecenas a auctor erat.';

// Encode the data, returns a BarcodeData object
$pdf417 = new PDF417();
$pdf417->encode($text);

// Create a PNG image
$pdf417->toFile('temp/example.basic.png');

// Create an SVG representation
$pdf417->configure(['color' => new pColor(0)]);
$pdf417->toFile("temp/example.basic.svg");

?>