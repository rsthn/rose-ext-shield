<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Text;

class MinItems extends Rule
{
	public function getName ()
	{
		return 'min-items';
	}

	public function validate ($name, &$val, $input, $output, $context, $errors)
	{
		$value = (int)$this->getValue($context);
		$this->identifier = $value;

        $type = \Rose\typeOf($val);
        if ($type !== 'Rose\Arry' && $type !== 'Rose\Map')
            return false;

		return $val->length() >= $value;
	}
};

Shield::registerRule('min-items', 'Rose\Ext\Shield\MinItems');
