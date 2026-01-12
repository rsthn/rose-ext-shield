<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;

class MinValue extends Rule
{
    public function getName() {
        return 'min-value';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        if (!\Rose\isInteger($value) && !\Rose\isNumber($value))
            throw new Error('reference value expected to be numeric');

        if (!\Rose\isInteger($val) && !\Rose\isNumber($val))
            throw new Error('argument expected to be numeric');

        $this->identifier = $value;
        return (float)$val >= (float)$value;
    }
};

Shield::registerRule('min-value', 'Rose\Ext\Shield\MinValue');
