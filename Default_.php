<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;

class Default_ extends Rule
{
    public function getName ()
    {
        return 'default';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        if (\Rose\isString($val))
            $val = Text::trim($val);

        if (!$input->has($name) || (\Rose\isString($val) && Text::length($val) == 0))
            $val = $this->getValue($context);

        return true;
    }
};

Shield::registerRule('default', 'Rose\Ext\Shield\Default_');
