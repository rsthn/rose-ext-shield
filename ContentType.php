<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Gateway;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;
use Rose\Errors\Error;

class ContentType extends Rule
{
	public function getName ()
	{
		return 'content-type';
	}

	public function validate ($name, &$val, $input, $output, $context, $errors)
	{
		$value = Text::toLowerCase($this->getValue($context));

		if (Gateway::getInstance()->input->contentType !== $value)
			return false;

		$val = Gateway::getInstance()->input->data;
		return true;
	}
};

Shield::registerRule('content-type', 'Rose\Ext\Shield\ContentType');
