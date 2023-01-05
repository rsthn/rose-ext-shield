<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;

class Cast extends Rule
{
	public function getName ()
	{
		return 'cast';
	}

	public function validate ($name, &$val, $input, $output, $context, $errors)
	{
		$value = $this->getValue($context);
		$this->identifier = $value;

		switch ($value)
		{
			case 'boolean':
				$val = \Rose\bool($val);
				break;

			case 'integer':
				$val = (int)$val;
				break;

			case 'number':
				$val = (double)$val;
				break;

			case 'string':
				$val = Text::toString($val);
				break;

			case 'null':
				$val = null;
				break;

			default:
				throw new Error('undefined_type: ' . $value);
		}

		return true;
	}
};

Shield::registerRule('cast', 'Rose\Ext\Shield\Cast');
