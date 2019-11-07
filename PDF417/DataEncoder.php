<?php

namespace PDF417;

/**
 * Encodes data into PDF417 code words.
 *
 * Top level data encoder which assigns encoding to lower level (byte, number, text) encoders.
 */
class DataEncoder
{
    private $encoders;
    private $options;

    public function __construct(array $options)
    {
        // Encoders sorted in order of preference
        $this->encoders = [
            new Encoders\NumberEncoder(),
            new Encoders\TextEncoder(),
            new Encoders\ByteEncoder(),
        ];

        $this->options = $options;
    }

    /**
     * Splits the input data into chains. Then encodes each chain.
     */
    public function encode($data)
    {
		switch($this->options["hint"]){
			case "numbers":
				$chains = [[$data, 0]];
				break;
			case "text":
				$chains = [[$data, 1]];
				break;
			case "binary":
				$chains = [[$data, 2]];
				break;
			default:
				$chains = $this->splitData($data);
		}

        // Decoders by default start decoding as text.
		// There is no point in adding the first switch code if it is text
		// Removed due to code compression

        $codes = [];
        foreach ($chains as $chain) {
			$codes = array_merge($codes, $this->encoders[$chain[1]]->encode($chain[0]));
        }

        return $codes;
    }

    /**
     * Splits a string into chains (sub-strings) which can be encoded with the same encoder.
     */
    private function splitData($data)
    {
        $length = strlen($data);
		$chains = [];

        for ($i = 0; $i < $length; $i++) {

			$e = $this->getEncoder($data[$i], $i);
			$chain = $data[$i];
			$end = false;

			while($this->encoders[$e]->canEncode($data[$i])){
				$i++;
				if (isset($data[$i])){
					$chain .= $data[$i];
				} else {
					$end = TRUE;
					break;
				}
			}

			if (!$end){
				$chain = substr($chain, 0, -1);
				$i--;
			}
			$chains[] = [$chain, $e];
		}

		return $chains;
    }

	public function getEncoder($char, $pos)
    {
        foreach ($this->encoders as $id => $encoder) {
            if ($encoder->canEncode($char)) {
                return $id;
            }
        }

        throw pException::InternalError("Cannot encode character at position ".($pos+1));
    }

}
