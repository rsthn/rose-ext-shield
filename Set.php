<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;

class Set extends Rule
{
	public function getName ()
	{
		return 'set';
	}

	public function validate ($name, &$val, $input, $output, $context, $errors)
	{
		$val = $this->getValue($context);
		return true;
	}
};

Shield::registerRule('set', 'Rose\Ext\Shield\Set');
