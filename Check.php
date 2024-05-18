<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;

class Check extends Rule
{
    public function getName ()
    {
        return 'check';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        return \Rose\bool($this->getValue($context));
    }
};

Shield::registerRule('check', 'Rose\Ext\Shield\Check');
