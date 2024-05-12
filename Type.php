<?php

namespace Rose\Ext\Shield;

use Rose\Errors\Error;
use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;
use Rose\Map;

class Type extends Rule
{
	public function getName ()
	{
		return 'type';
	}

	public function getIdentifier()
	{
		return '';
	}

	public function validate ($name, &$val, $input, $output, $context, $errors)
	{
		$value = $this->getValue($context);
		$_errors = new Map();

		$tmp = new Map();
		$tmp->set('tmp', $val);
		$val = Shield::validateValue ($value, 'tmp', 'tmp', $tmp, $tmp, $context, $_errors);

		if ($_errors->length)
		{
			$_errors->forEach(function($value, $key) use($name, $errors) {
				if (Text::startsWith($key, 'tmp'))
					$errors->set($name, $value);
			});

			throw new Error('');
		}

		if (!$tmp->has('tmp'))
			throw new IgnoreField();

		$val = $tmp->get('tmp');
		return true;
	}
};

Shield::registerRule('type', 'Rose\Ext\Shield\Type');
