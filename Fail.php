<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;

class Fail extends Rule
{
    public function getName ()
    {
        return 'fail';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        return !\Rose\bool($this->getValue($context));
    }
};

Shield::registerRule('fail', 'Rose\Ext\Shield\Fail');
