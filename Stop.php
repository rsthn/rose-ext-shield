<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;

class Stop extends Rule
{
    public function getName() {
        return 'stop';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        if (!\Rose\isBool($value))
            throw new Error('reference value expected to be bool');
        if ($value)
            throw new StopValidation();
        return true;
    }
};

Shield::registerRule('stop', 'Rose\Ext\Shield\Stop');
