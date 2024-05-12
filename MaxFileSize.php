<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\IO\Path;
use Rose\Text;

class MaxFileSize extends Rule
{
	public function getName ()
	{
		return 'max-file-size';
	}

	public function validate ($name, &$val, $input, $output, $context, $errors)
	{
		$value = $this->getValue($context);
		$this->identifier = $value;

		if (\Rose\typeOf($val) != 'Rose\\Map')
			return false;

		if ($val->error != 0)
			return false;

		if ((int)$val->size > (int)$value)
			return false;

		return true;
	}
};

Shield::registerRule('max-file-size', 'Rose\Ext\Shield\MaxFileSize');
