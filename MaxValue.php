<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Text;

class MaxValue extends Rule
{
    public function getName ()
    {
        return 'max-value';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        $this->identifier = $value;

        return (float)$val <= (float)$value;
    }
};

Shield::registerRule('max-value', 'Rose\Ext\Shield\MaxValue');
