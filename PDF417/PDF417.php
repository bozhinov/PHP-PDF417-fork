<?php

namespace PDF417;

use PDF417\Renderer;
use PDF417\pColor;
use PDF417\pException;

class PDF417
{
    private $_START_CHARACTER = 0x1fea8;
    private $_STOP_CHARACTER  = 0x3fa29;
    private $options = [];

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

	private function render()
	{
		return (new Renderer($this->pixelGrid, $this->options));
	}

	public function toFile(string $filename, bool $forWeb = false)
	{
		$ext = strtoupper(substr($filename, -3));
		($forWeb) AND $filename = null;

		$renderer = $this->render();

		switch($ext)
		{
			case "PNG":
				$renderer->toPNG($filename);
				break;
			case "GIF":
				$renderer->toGIF($filename);
				break;
			case "JPG":
				$renderer->toJPG($filename, $this->options['quality']);
				break;
			case "SVG":
				$content = $renderer->createSVG();
				if(is_null($filename)) {
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
			return ($this->render())->toBase64();
		} else {
			$this->toFile($ext, true);
		}
	}

	public function forPChart(\pChart\pDraw $MyPicture, $X = 0, $Y = 0)
	{
		($this->render())->forPChart($MyPicture->gettheImage(), $X, $Y);
	}

    /**
     * Encodes the given data to low level code words.
     */
    public function encode($data)
    {
        $codeWords = $this->encodeData($data);

        // Arrange codewords into a rows and columns
        $grid = array_chunk($codeWords, $this->options['columns']);
        $rows = count($grid);

        // Iterate over rows
        $this->pixelGrid = [];
        foreach ($grid as $rowNum => $row) {

            $table = $rowNum % 3;

			// Add starting code word
            $rowCodes = [$this->_START_CHARACTER];

            // Add left-side code word
            $left = $this->getLeftCodeWord($rowNum, $rows);
            $rowCodes[] = Codes::getCode($table, $left);

            // Add data code words
            foreach ($row as $word) {
                $rowCodes[] = Codes::getCode($table, $word);
            }

            // Add right-side code word
            $right = $this->getRightCodeWord($rowNum, $rows);
            $rowCodes[] = Codes::getCode($table, $right);

            // Add ending code word
            $rowCodes[] = $this->_STOP_CHARACTER;

			$pixelRow = [];
			foreach ($rowCodes as $value) {
                $bin = decbin($value);
                $len = strlen($bin);
                for ($i = 0; $i < $len; $i++) {
                    $pixelRow[] = (boolean) $bin[$i];
                }
            }

			 $this->pixelGrid[] = $pixelRow;
        }
    }

    /* Encodes data to a grid of codewords for constructing the barcode. */
    public function encodeData($data)
    {
        // Encode data to code words
        $dataWords = (new DataEncoder($this->options))->encode($data);

        // Number of code correction words
        $ecCount = pow(2, $this->options['securityLevel'] + 1);
        $dataCount = count($dataWords);

        // Add padding if needed
        $padWords = $this->getPadding($dataCount, $ecCount);
        $dataWords = array_merge($dataWords, $padWords);

        // Add length specifier as the first data code word
        // Length includes the data CWs, padding CWs and the specifier itself
        $length = count($dataWords) + 1;
        array_unshift($dataWords, $length);

        // Compute error correction code words
        $reedSolomon = new ReedSolomon();
        $ecWords = $reedSolomon->compute($dataWords, $this->options['securityLevel']);

        // Combine the code words and return
        return array_merge($dataWords, $ecWords);
    }

    private function getLeftCodeWord($rowNum, $rows)
    {
        // Table used to encode this row
        $tableID = $rowNum % 3;

        switch($tableID) {
            case 0:
                $x = intval(($rows - 1) / 3);
                break;
            case 1:
                $x = $this->options['securityLevel'] * 3;
                $x += ($rows - 1) % 3;
                break;
            case 2:
                $x = $this->options['columns'] - 1;
                break;
        }

        return 30 * intval($rowNum / 3) + $x;
    }

    private function getRightCodeWord($rowNum, $rows)
    {
        $tableID = $rowNum % 3;

        switch($tableID) {
            case 0:
                $x = $this->options['columns'] - 1;
                break;
            case 1:
                $x = intval(($rows - 1) / 3);
                break;
            case 2:
                $x = $this->options['securityLevel'] * 3;
                $x += ($rows - 1) % 3;
                break;
        }

        return 30 * intval($rowNum / 3) + $x;
    }

    private function getPadding($dataCount, $ecCount)
    {
        // Total number of data words and error correction words, additionally
        // reserve 1 code word for the length descriptor
        $totalCount = $dataCount + $ecCount + 1;
        $mod = $totalCount % $this->options['columns'];

        if ($mod > 0) {
            $padCount = $this->options['columns'] - $mod;
            $padding = array_fill(0, $padCount, 900);
        } else {
            $padding = [];
        }

        return $padding;
    }
}
