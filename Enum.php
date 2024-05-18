<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;

class Enum extends Rule
{
    public function getName ()
    {
        return 'enum';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);

        $type = \Rose\typeOf($value);
        if ($type === 'Rose\Arry')
            return $value->indexOf($val) !== null;

        if ($type === 'Rose\Map')
            return $value->has($val);

        return Text::split(',', $value)->map(function ($v) { return Text::trim($v); })->indexOf($val) !== null;
    }
};

Shield::registerRule('enum', 'Rose\Ext\Shield\Enum');
