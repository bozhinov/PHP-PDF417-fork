<?php

namespace PDF417;

class PDF417
{
	private $options = [];
	private $renderer;

	public function __construct(array $options = [])
	{
		$this->options['color'] = (isset($options['color'])) ? $options['color'] : new pColor(0);
		$this->options['bgColor'] = (isset($options['bgColor'])) ? $options['bgColor'] : new pColor(255);
		/**
		* Number of data columns in the bar code.
		*
		* The total number of columns will be greater due to adding start, stop,
		* left and right columns.
		*/
		$this->options['columns'] = (isset($options['columns'])) ? $options['columns'] : 6;
		/**
		* Can be used to force binary encoding. This may reduce size of the
		* barcode if the data contains many encoder changes, such as when
		* encoding a compressed file.
		*/
		$this->options['securityLevel'] = (isset($options['securityLevel'])) ? $options['securityLevel'] : 2;
		$this->options['hint'] = (isset($options['hint'])) ? $options['hint'] : "none";
		$this->options['scale'] = (isset($options['scale'])) ? $options['scale'] : 3;
		$this->options['ratio'] = (isset($options['ratio'])) ? $options['ratio'] : 3;
		$this->options['padding'] = (isset($options['padding'])) ? $options['padding'] : 20;
		$this->options['quality'] = (isset($options['quality'])) ? $options['quality'] : 90;

		$this->validateOptions();
	}

	public function config(array $options)
	{
		$this->__construct($options);
	}

	private function option_in_range(string $name, int $start, int $end)
	{
		if (!is_numeric($this->options[$name]) || $this->options[$name] < $start || $this->options[$name] > $end) {
			throw pException::InvalidInput("Invalid value for \"$name\". Expected an integer between $start and $end.");
		}
	}

	private function validateOptions()
	{
		$this->option_in_range('scale', 1, 20);
		$this->option_in_range('ratio', 1, 10);
		$this->option_in_range('padding', 0, 50);
		$this->option_in_range('columns', 1, 30);
		$this->option_in_range('securityLevel', 0, 8);
		$this->option_in_range('quality', 0, 100);

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
		$pixelGrid = (new DataEncoder($this->options))->encodeData($data);
		$this->renderer = (new Renderer($pixelGrid, $this->options));
	}
}
