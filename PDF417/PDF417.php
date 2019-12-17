<?php

namespace PDF417;

use PDF417\Encoder\Encoder;

class PDF417
{
	private $options = [];
	private $renderer;

	public function __construct(array $opts = [])
	{
		$this->options['color'] = (isset($opts['color'])) ? $opts['color'] : new pColor(0);
		$this->options['bgColor'] = (isset($opts['bgColor'])) ? $opts['bgColor'] : new pColor(255);
		/**
		* Number of data columns in the bar code.
		*
		* The total number of columns will be greater due to adding start, stop,
		* left and right columns.
		*/
		$this->options['columns'] = (isset($opts['columns'])) ? $this->option_in_range($opts['columns'], 1, 30) : 6;
		/**
		* Can be used to force binary encoding. This may reduce size of the
		* barcode if the data contains many encoder changes, such as when
		* encoding a compressed file.
		*/
		$this->options['hint'] = (isset($opts['hint'])) ? $opts['hint'] : "none";
		$this->options['scale'] = (isset($opts['scale'])) ? $this->option_in_range($opts['scale'], 1, 20) : 3;
		$this->options['ratio'] = (isset($opts['ratio'])) ? $this->option_in_range($opts['ratio'], 1, 10) : 3;
		$this->options['padding'] = (isset($opts['padding'])) ? $this->option_in_range($opts['padding'], 0, 50) : 20;
		$this->options['quality'] = (isset($opts['quality'])) ? $this->option_in_range($opts['quality'], 0, 100) : 90;
		$this->options['securityLevel'] = (isset($opts['securityLevel'])) ? $this->option_in_range($opts['securityLevel'], 0, 8) : 2;

		$this->validateOptions();
	}

	public function config(array $options)
	{
		$this->__construct($options);
	}

	private function option_in_range($value, int $start, int $end)
	{
		if (!is_numeric($value) || $value < $start || $value > $end) {
			throw pException::InvalidInput("Invalid value. Expected an integer between $start and $end.");
		}

		return $value;
	}

	private function validateOptions()
	{

		if (!in_array($this->options["hint"], ["binary", "numbers", "text", "none"])){
			throw pException::InvalidInput("Invalid value for \"hint\". Expected \"binary\", \"numbers\" or \"text\".");
		}

		if (!($this->options['color'] instanceof pColor)) {
			throw pException::InvalidInput("Invalid value for \"color\". Expected a pColor object.");
		}

		if (!($this->options['bgColor'] instanceof pColor)) {
			throw pException::InvalidInput("Invalid value for \"bgColor\". Expected a pColor object.");
		}
	}

	public function toFile(string $filename, bool $forWeb = false)
	{
		$ext = strtoupper(substr($filename, -3));
		($forWeb) AND $filename = null;

		switch($ext)
		{
			case "PNG":
				$this->renderer->toPNG($filename);
				break;
			case "GIF":
				$this->renderer->toGIF($filename);
				break;
			case "JPG":
				$this->renderer->toJPG($filename, $this->options['quality']);
				break;
			case "SVG":
				$content = $this->renderer->createSVG();
				if($forWeb) {
					header("Content-type: image/svg+xml");
					return $content;
				} else {
					file_put_contents($filename, $content);
				}
				break;
			default:
				throw pException::InvalidInput('File extension unsupported!');
		}
	}

	public function forWeb(string $ext)
	{
		if (strtoupper($ext) == "BASE64"){
			return $this->renderer->toBase64();
		} else {
			$this->toFile($ext, true);
		}
	}

	public function forPChart(\pChart\pDraw $MyPicture, $X = 0, $Y = 0)
	{
		$this->renderer->forPChart($MyPicture->gettheImage(), $X, $Y);
	}

	public function encode($data)
	{
		$pixelGrid = (new Encoder($this->options))->encodeData($data);
		$this->renderer = (new Renderer($pixelGrid, $this->options));
	}
}
