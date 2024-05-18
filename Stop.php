<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;

class Stop extends Rule
{
    public function getName ()
    {
        return 'stop';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        if (\Rose\bool($this->getValue($context)))
            throw new StopValidation();

        return true;
    }
};

Shield::registerRule('stop', 'Rose\Ext\Shield\Stop');
