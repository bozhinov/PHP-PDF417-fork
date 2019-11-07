<?php

require_once("bootstrap.php");

use PDF417\PDF417;

// Text to be encoded into the barcode
$text = '123123123123123123123123123123';

// Encode the data, returns a BarcodeData object
$pdf417 = new PDF417();
$pdf417->encode($text);

// Create a PNG image
$pdf417->toFile('temp/example.numbers.png');


?>