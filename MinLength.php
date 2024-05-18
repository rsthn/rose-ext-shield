<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Text;

class MinLength extends Rule
{
    public function getName ()
    {
        return 'min-length';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = (int)$this->getValue($context);
        $this->identifier = $value;

        return Text::length(Text::toString($val)) >= $value;
    }
};

Shield::registerRule('min-length', 'Rose\Ext\Shield\MinLength');
