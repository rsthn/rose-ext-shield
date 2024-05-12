<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Text;

class Length extends Rule
{
	public function getName ()
	{
		return 'length';
	}

	public function validate ($name, &$val, $input, $output, $context, $errors)
	{
		$value = (int)$this->getValue($context);
		$this->identifier = $value;

		return Text::length(Text::toString($val)) == $value;
	}
};

Shield::registerRule('length', 'Rose\Ext\Shield\Length');
