<?php

namespace PDF417;

class Renderer
{
	private $image;
	private $pixelGrid;
	private $options;

	function __construct(array $pixelGrid, array $options)
	{
		$this->pixelGrid = $pixelGrid;
		$this->options = $options;
	}

	function __destruct()
	{
		if (is_resource($this->image)){
			imagedestroy($this->image);
		}
	}

	public function toBase64()
	{
		$this->createImage();
		ob_start();
		imagePng($this->image);
		$imagedata = ob_get_contents();
		ob_end_clean();

		return base64_encode($imagedata);
	}

	public function toPNG($filename)
	{
		$this->createImage();
		if(is_null($filename)) {
			header("Content-type: image/png");
		}
		imagepng($this->image, $filename);
	}

	public function toGIF($filename)
	{
		$this->createImage();
		if(is_null($filename)) {
			header("Content-type: image/gif");
		}
		imagegif($this->image, $filename);
	}

	public function toJPG($filename, $quality)
	{
		$this->createImage();
		if(is_null($filename)) {
			header("Content-type: image/jpeg");
		}
		imagejpeg($this->image, $filename, $quality);
	}

	public function forPChart($pImage, $X, $Y)
	{
		$this->createImage();
		imagecopy($pImage, $this->image, $X, $Y, 0, 0, imagesx($this->image), imagesy($this->image));
	}

	private function createImage()
	{
		$padding = $this->options['padding'];

		$width = count($this->pixelGrid[0]);
		$height = count($this->pixelGrid);

		$scaleX = $this->options['scale'];
		$scaleY = $this->options['scale'] * $this->options['ratio'];

		// Apply scaling & aspect ratio
		$width = ($width * $scaleX) + $padding * 2;
		$height = ($height * $scaleY) + $padding * 2;

		$this->image = imagecreate($width, $height);

		// Extract options
		list($R,$G,$B) = $this->options['bgColor']->get();
		$bgColorAlloc = imagecolorallocate($this->image,$R,$G,$B);
		imagefill($this->image, 0, 0, $bgColorAlloc);
		list($R,$G,$B) = $this->options['color']->get();
		$colorAlloc = imagecolorallocate($this->image,$R,$G,$B);

		// Render the barcode
		foreach ($this->pixelGrid as $y => $row) {
			foreach ($row as $x => $value) {
				if ($value) {
					imagefilledrectangle(
						$this->image,
						($x * $scaleX) + $padding,
						($y * $scaleY) + $padding,
						(($x + 1) * $scaleX - 1) + $padding,
						(($y + 1) * $scaleY - 1) + $padding,
						$colorAlloc
					);
				}
			}
		}
	}

	public function createSVG()
	{
		$height = count($this->pixelGrid);
		$width = count($this->pixelGrid[0]);

		// Apply scaling & aspect ratio
		$scaleX = $this->options['scale'];
		$scaleY = $this->options['scale'] * $this->options['ratio'];

		$width *= $scaleX;
		$height *= $scaleY;

		$doc = $this->createDocument();

		// Root document
		$svg = $doc->createElement("svg");
		$svg->setAttribute("height", $height);
		$svg->setAttribute("width", $width);
		$svg->setAttribute("version", "1.1");
		$svg->setAttribute("xmlns", "http://www.w3.org/2000/svg");

		// Create the group
		$group = $doc->createElement("g");
		$group->setAttribute('id', 'barcode');
		$group->setAttribute('fill', $this->options['color']->toHex());
		$group->setAttribute('stroke', 'none');

		// Add barcode elements to group
		foreach ($this->pixelGrid as $y => $row) {
			foreach ($row as $x => $item) {
				if ($item === false) {
					continue;
				}

				$rect = $doc->createElement('rect');
				$rect->setAttribute("x", $x * $scaleX);
				$rect->setAttribute("y", $y * $scaleY);
				$rect->setAttribute("width", $scaleX);
				$rect->setAttribute("height", $scaleY);

				$group->appendChild($rect);
			}
		}

		$svg->appendChild($group);
		$doc->appendChild($svg);

		return $doc->saveXML();
	}

	/** Creates a DOMDocument for SVG. */
	private function createDocument()
	{
		$impl = new \DOMImplementation();

		$docType = $impl->createDocumentType(
		"svg",
		"-//W3C//DTD SVG 1.1//EN",
		"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"
		);

		$doc = $impl->createDocument(null, null, $docType);
		$doc->formatOutput = true;

		return $doc;
	}
}
