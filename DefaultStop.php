<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;

class DefaultStop extends Rule
{
    public function getName ()
    {
        return 'default-stop';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        if (\Rose\isString($val))
            $val = Text::trim($val);

        if (!$input->has($name) || (\Rose\isString($val) && Text::length($val) == 0)) {
            $val = $this->getValue($context);
            throw new StopValidation();
        }

        return true;
    }
};

Shield::registerRule('default-stop', 'Rose\Ext\Shield\DefaultStop');
