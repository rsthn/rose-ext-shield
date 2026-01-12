<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;

class UniqueItems extends Rule
{
    public function getName() {
        return 'unique-items';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        if (!\Rose\isBool($value))
            throw new Error('reference value expected to be bool');

        if (!$value)
            return true;

        $type = \Rose\typeOf($val);
        if ($type !== 'Rose\Arry') {
            throw new Error('argument expected to be array');
        }

        return $val->unique()->length() == $val->length();
    }
};

Shield::registerRule('unique-items', 'Rose\Ext\Shield\UniqueItems');
