<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;

class Length extends Rule
{
    public function getName() {
        return 'length';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        if (!\Rose\isInteger($value) && !\Rose\isNumber($value))
            throw new Error('reference value expected to be numeric');

        if (!\Rose\isString($val))
            throw new Error('argument expected to be string');

        $this->identifier = $value;
        return Text::length(Text::toString($val)) == $value;
    }
};

Shield::registerRule('length', 'Rose\Ext\Shield\Length');
