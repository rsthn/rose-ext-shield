<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;

class Expect extends Rule
{
	public function getName ()
	{
		return 'expect';
	}

	public function validate ($name, &$val, $input, $output, $context, $errors)
	{
		$value = $this->getValue($context);
		$this->identifier = $value;

		switch ($value)
		{
			case 'boolean': case 'bool':
				return \Rose\isBool($val);

			case 'integer': case 'int':
				return \Rose\isInteger($val);

			case 'number':
				return \Rose\isNumber($val);

			case 'string': case 'str':
				return \Rose\isString($val);

			case 'null':
				return $val === null;

			default:
				throw new Error('undefined_type: ' . $value);
		}

		return true;
	}
};

Shield::registerRule('expect', 'Rose\Ext\Shield\Expect');
