<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;

class MaxItems extends Rule
{
    public function getName() {
        return 'max-items';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        if (!\Rose\isInteger($value) && !\Rose\isNumber($value))
            throw new Error('reference value expected to be numeric');

        $type = \Rose\typeOf($val);
        if ($type !== 'Rose\Arry' && $type !== 'Rose\Map')
            throw new Error('argument expected to be array or object');

        $this->identifier = $value;
        return $val->length() <= $value;
    }
};

Shield::registerRule('max-items', 'Rose\Ext\Shield\MaxItems');
