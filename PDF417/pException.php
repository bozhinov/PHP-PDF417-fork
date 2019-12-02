<?php

namespace PDF417;

class pException extends \Exception
{
	public static function InvalidInput($text)
	{
		return new static(sprintf('PDF417: %s', $text));
	}

	public static function InternalError($text)
	{
		return new static(sprintf('PDF417: %s', $text));
	}

	public static function EncoderError($text)
	{
		return new static(sprintf('PDF417: %s', $text));
	}
}
